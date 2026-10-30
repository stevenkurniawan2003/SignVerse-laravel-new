<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\GestureResult;
use App\Models\Lesson;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Teacher module (phase 7): statistik kelas, monitoring siswa,
 * dan analisis error gesture dari gesture_results.
 *
 * Semua angka agregat dihitung dari practice_sessions (completed),
 * assignment_submissions, dan gesture_results — tanpa kolom denormalisasi.
 */
class TeacherStatsService
{
    /* ==========================================================================
     | Ringkasan kelas (dashboard)
     * ========================================================================*/

    /**
     * Ringkasan kelas untuk dashboard guru.
     *
     * @return array{students: int, weekly_sessions: int, avg_class_score: float, active_assignments: int}
     */
    public function classOverview(Teacher $teacher): array
    {
        $completed = PracticeSession::query()
            ->where('status', PracticeSession::STATUS_COMPLETED);

        return [
            'students' => Student::count(),
            'weekly_sessions' => (int) (clone $completed)
                ->where('completed_at', '>=', now()->startOfWeek())
                ->count(),
            'avg_class_score' => round((float) (clone $completed)->avg('score'), 1),
            'active_assignments' => $this->activeAssignments($teacher)->count(),
        ];
    }

    /**
     * Assignment milik guru yang belum melewati deadline (atau tanpa deadline).
     */
    public function activeAssignments(Teacher $teacher): Collection
    {
        return $teacher->assignments()
            ->with('lesson.material.category')
            ->where(fn ($query) => $query
                ->whereNull('due_at')
                ->orWhere('due_at', '>=', now()))
            // Nulls-last: deadline terdekat lebih dulu, tanpa deadline di akhir
            // (berlaku di MySQL maupun SQLite).
            ->orderByRaw('due_at IS NULL, due_at')
            ->get();
    }

    /* ==========================================================================
     | Monitoring per siswa
     * ========================================================================*/

    /**
     * Semua siswa dengan ringkasan aktivitas untuk tabel monitoring.
     *
     * @return Collection<int, array{student: Student, name: string, sessions: int, avg_score: float, xp: int, level: int, streak: int, last_activity: Carbon|null}>
     */
    public function studentsOverview(): Collection
    {
        return Student::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Student $student) => $this->studentSummary($student))
            ->sortByDesc('sessions')
            ->values();
    }

    /**
     * Ringkasan satu siswa (dipakai di tabel monitoring & halaman detail).
     *
     * @return array{student: Student, name: string, sessions: int, avg_score: float, xp: int, level: string, streak: int, last_activity: Carbon|null}
     */
    public function studentSummary(Student $student): array
    {
        $completed = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED);

        $lastCompletedAt = (clone $completed)->max('completed_at');
        $totalXp = $student->totalXp();

        return [
            'student' => $student,
            'name' => $student->user?->name ?? 'Siswa #'.$student->id,
            'sessions' => (int) (clone $completed)->count(),
            'avg_score' => round((float) (clone $completed)->avg('score'), 1),
            'xp' => $totalXp,
            'level' => GamificationService::levelFromXp($totalXp)['name'],
            'streak' => GamificationService::streakInfo($student->user_id)['streak'],
            'last_activity' => $lastCompletedAt !== null
                ? Carbon::parse($lastCompletedAt)
                : $student->last_activity_date,
        ];
    }

    /**
     * Detail lengkap satu siswa untuk halaman monitoring detail.
     *
     * @return array{
     *     summary: array, recent_sessions: Collection, category_progress: Collection, achievements: Collection,
     *     assignments: Collection, challenges_today: Collection, challenge_rewards: Collection
     * }
     */
    public function studentDetail(Student $student): array
    {
        $recentSessions = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->with(['lesson.material.category'])
            ->latest('completed_at')
            ->limit(5)
            ->get();

        return [
            'summary' => $this->studentSummary($student),
            'recent_sessions' => $recentSessions,
            'category_progress' => app(StudentStatsService::class)->progressByCategory($student),
            'achievements' => collect(GamificationService::achievements($student->user_id))
                ->filter(fn (array $badge) => $badge['unlocked'])
                ->sortByDesc(fn (array $badge) => $badge['unlocked_at'] ?? '')
                ->values(),
            'assignments' => $student->submissions()
                ->with('assignment.lesson')
                ->latest()
                ->get(),
            'challenges_today' => ChallengeService::challengeStatus($student->user_id),
            'challenge_rewards' => ChallengeService::challengeRewards($student->user_id)->take(5),
        ];
    }

    /* ==========================================================================
     | Monitoring realtime (di-poll halaman guru)
     * ========================================================================*/

    /**
     * Ringkasan kelas milik guru: hanya siswa yang tergabung di kelasnya.
     *
     * @return array{classes: int, students: int, active_today: int, weekly_sessions: int, avg_score: float, active_assignments: int}
     */
    public function teacherOverview(Teacher $teacher): array
    {
        $studentIds = $teacher->studentsQuery()->pluck('students.id');
        $completed = PracticeSession::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', PracticeSession::STATUS_COMPLETED);

        return [
            'classes' => $teacher->classrooms()->count(),
            'students' => $studentIds->count(),
            'active_today' => (int) (clone $completed)->where('completed_at', '>=', today())->distinct('student_id')->count('student_id'),
            'weekly_sessions' => (int) (clone $completed)->where('completed_at', '>=', now()->startOfWeek())->count(),
            'avg_score' => round((float) (clone $completed)->avg('score'), 1),
            'active_assignments' => $this->activeAssignments($teacher)->count(),
        ];
    }

    /**
     * Tabel siswa guru (opsional satu kelas) dengan agregat dalam satu query:
     * jumlah latihan, rata-rata skor, huruf dikuasai, XP, streak, aktivitas terakhir.
     *
     * @return Collection<int, array{id: int, user_id: int, name: string, email: string, sessions: int, avg_score: float, mastered: int, xp: int, level: string, streak: int, last_activity: ?string, classes: string[]}>
     */
    public function studentsTable(Teacher $teacher, ?Classroom $classroom = null): Collection
    {
        $sessions = DB::table('practice_sessions')
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->groupBy('student_id')
            ->selectRaw('student_id, COUNT(*) as sessions, AVG(score) as avg_score, MAX(completed_at) as last_activity, SUM(xp_earned) as xp')
            ->selectRaw('COUNT(DISTINCT CASE WHEN score >= ? THEN lesson_id END) as mastered', [PracticeService::PASS_SCORE]);

        $challengeXp = DB::table('challenge_progress as cp')
            ->join('challenges as c', 'c.id', '=', 'cp.challenge_id')
            ->where('cp.completed', 1)
            ->groupBy('cp.user_id')
            ->selectRaw('cp.user_id, SUM(c.reward_xp) as xp');

        $assignmentXp = DB::table('assignment_submissions')
            ->where('status', AssignmentSubmission::STATUS_COMPLETED)
            ->groupBy('student_id')
            ->selectRaw('student_id, SUM(xp_earned) as xp');

        $studentIds = $classroom
            ? $classroom->students()->pluck('students.id')
            : $teacher->studentsQuery()->pluck('students.id');

        $classNames = DB::table('classroom_student as cs')
            ->join('classrooms as c', 'c.id', '=', 'cs.classroom_id')
            ->where('c.teacher_id', $teacher->id)
            ->whereIn('cs.student_id', $studentIds)
            ->get(['cs.student_id', 'c.name'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('name')->all());

        return DB::table('students as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoinSub($sessions, 'p', 'p.student_id', '=', 's.id')
            ->leftJoinSub($challengeXp, 'ch', 'ch.user_id', '=', 's.user_id')
            ->leftJoinSub($assignmentXp, 'a', 'a.student_id', '=', 's.id')
            ->whereIn('s.id', $studentIds)
            ->orderBy('u.name')
            ->get([
                's.id', 's.user_id', 'u.name', 'u.email', 's.current_streak',
                'p.sessions', 'p.avg_score', 'p.mastered', 'p.last_activity',
                DB::raw('COALESCE(p.xp, 0) + COALESCE(ch.xp, 0) + COALESCE(a.xp, 0) as total_xp'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'sessions' => (int) $row->sessions,
                'avg_score' => round((float) $row->avg_score, 1),
                'mastered' => (int) $row->mastered,
                'xp' => (int) $row->total_xp,
                'level' => GamificationService::levelFromXp((int) $row->total_xp)['name'],
                'streak' => (int) $row->current_streak,
                'last_activity' => $row->last_activity ? Carbon::parse($row->last_activity)->toIso8601String() : null,
                'classes' => $classNames->get($row->id, []),
            ]);
    }

    /**
     * Aktivitas latihan terbaru siswa guru (feed realtime dashboard).
     *
     * @return Collection<int, array{id: int, student_id: int, student: string, lesson: string, language: string, score: int, passed: bool, xp: int, at: ?string}>
     */
    public function recentActivity(Teacher $teacher, int $limit = 12): Collection
    {
        return PracticeSession::query()
            ->whereIn('student_id', $teacher->studentsQuery()->select('students.id'))
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->with(['student.user:id,name', 'lesson:id,title'])
            ->latest('completed_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (PracticeSession $session) => [
                'id' => $session->id,
                'student_id' => $session->student_id,
                'student' => $session->student?->user?->name ?? 'Siswa',
                'lesson' => $session->lesson?->title ?? '-',
                'language' => strtoupper($session->language),
                'score' => (int) $session->score,
                'passed' => (int) $session->score >= PracticeService::PASS_SCORE,
                'xp' => (int) $session->xp_earned,
                'at' => $session->completed_at?->toIso8601String(),
            ]);
    }

    /**
     * Huruf yang sudah dikuasai siswa per sistem isyarat (untuk grid A–Z guru).
     *
     * @return array<string, array<int, array{letter: string, mastered: bool, best_score: int}>>
     */
    public function letterMastery(Student $student): array
    {
        $best = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->groupBy('lesson_id')
            ->selectRaw('lesson_id, MAX(score) as best')
            ->pluck('best', 'lesson_id');

        return Lesson::query()
            ->with('material:id,language,category_id', 'material.category:id,slug')
            ->whereHas('material.category', fn ($query) => $query->where('slug', 'alphabet'))
            ->orderBy('title')
            ->get()
            ->groupBy(fn (Lesson $lesson) => strtoupper($lesson->material->language))
            ->map(fn (Collection $lessons) => $lessons->map(fn (Lesson $lesson) => [
                'letter' => $lesson->title,
                'mastered' => (int) ($best[$lesson->id] ?? 0) >= PracticeService::PASS_SCORE,
                'best_score' => (int) ($best[$lesson->id] ?? 0),
            ])->values()->all())
            ->all();
    }

    /**
     * Teacher model dari user yang sedang login.
     * Profil dibuat otomatis jika belum ada (safety net untuk user lama).
     */
    public function forUser(User $user): Teacher
    {
        return $user->teacher ?? $user->teacher()->create();
    }

    /* ==========================================================================
     | Analisis error
     * ========================================================================*/

    /**
     * Analisis error: gesture salah terbanyak (opsional per lesson).
     *
     * @return Collection<int, array{lesson_id: int, lesson: string, gesture: string, wrong: int, total: int, accuracy: int}>
     */
    public function errorAnalysisForLesson(?int $lessonId = null, ?Teacher $teacher = null): Collection
    {
        $results = GestureResult::query()
            ->when($lessonId !== null, fn ($query) => $query->where('lesson_id', $lessonId))
            ->when($teacher !== null, fn ($query) => $query->whereHas(
                'practiceSession',
                fn ($session) => $session->whereIn('student_id', $teacher->studentsQuery()->select('students.id')),
            ))
            ->with('lesson:id,title')
            ->get();

        return $results
            ->groupBy(fn (GestureResult $result) => $result->lesson_id.'|'.$result->expected_gesture)
            ->map(fn (Collection $group) => [
                'lesson_id' => $group->first()->lesson_id,
                'lesson' => $group->first()->lesson?->title ?? 'Lesson #'.$group->first()->lesson_id,
                'gesture' => $group->first()->expected_gesture,
                'wrong' => $group->where('correct', false)->count(),
                'total' => $group->count(),
            ])
            ->map(function (array $row) {
                $row['accuracy'] = $row['total'] > 0
                    ? (int) round(($row['total'] - $row['wrong']) / $row['total'] * 100)
                    : 0;

                return $row;
            })
            ->filter(fn (array $row) => $row['wrong'] > 0)
            ->sortByDesc('wrong')
            ->take(10)
            ->values();
    }

    /**
     * Analisis error untuk satu siswa: gesture yang sering salah oleh siswa tsb.
     *
     * @return Collection<int, array{lesson_id: int, lesson: string, gesture: string, wrong: int, total: int, accuracy: int}>
     */
    public function errorAnalysisForStudent(Student $student): Collection
    {
        $results = GestureResult::query()
            ->whereHas('practiceSession', fn ($query) => $query->where('student_id', $student->id))
            ->with('lesson:id,title')
            ->get();

        return $results
            ->groupBy('expected_gesture')
            ->map(fn (Collection $group) => [
                'lesson_id' => $group->first()->lesson_id,
                'lesson' => $group->first()->lesson?->title ?? 'Lesson #'.$group->first()->lesson_id,
                'gesture' => $group->first()->expected_gesture,
                'wrong' => $group->where('correct', false)->count(),
                'total' => $group->count(),
            ])
            ->map(function (array $row) {
                $row['accuracy'] = $row['total'] > 0
                    ? (int) round(($row['total'] - $row['wrong']) / $row['total'] * 100)
                    : 0;

                return $row;
            })
            ->filter(fn (array $row) => $row['wrong'] > 0)
            ->sortByDesc('wrong')
            ->take(10)
            ->values();
    }

    /**
     * Akurasi rata-rata recognition (persentase gesture benar) per lesson
     * — untuk widget keseluruhan analisis error.
     *
     * @return Collection<int, array{lesson_id: int, lesson: string, total: int, correct: int, accuracy: int}>
     */
    public function lessonAccuracy(): Collection
    {
        return GestureResult::query()
            ->with('lesson:id,title')
            ->get()
            ->groupBy('lesson_id')
            ->map(fn (Collection $group, int $lessonId) => [
                'lesson_id' => $lessonId,
                'lesson' => $group->first()->lesson?->title ?? 'Lesson #'.$lessonId,
                'total' => $group->count(),
                'correct' => $group->where('correct', true)->count(),
            ])
            ->map(function (array $row) {
                $row['accuracy'] = $row['total'] > 0
                    ? (int) round($row['correct'] / $row['total'] * 100)
                    : 0;

                return $row;
            })
            ->sortBy('accuracy')
            ->values();
    }
}
