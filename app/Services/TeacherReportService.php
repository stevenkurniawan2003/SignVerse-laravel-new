<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\PracticeSession;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan belajar untuk guru: rekap latihan siswa di kelasnya pada periode
 * tertentu — ringkasan, aktivitas harian, per siswa, huruf tersulit, dan tugas.
 * Dipakai halaman Laporan dan ekspor CSV.
 */
class TeacherReportService
{
    /** Siswa dianggap "kurang aktif" bila berlatih kurang dari ini dalam periode. */
    private const LOW_ACTIVITY_SESSIONS = 3;

    /**
     * @return array{
     *     period: array{from: string, to: string, days: int},
     *     classroom: ?array{id: int, name: string},
     *     summary: array<string, int|float>,
     *     daily: array<int, array{date: string, sessions: int, passed: int}>,
     *     students: Collection<int, array<string, mixed>>,
     *     letters: Collection<int, array{letter: string, language: string, attempts: int, passed: int, pass_rate: int}>,
     *     assignments: Collection<int, array<string, mixed>>
     * }
     */
    public function build(Teacher $teacher, ?Classroom $classroom, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $studentIds = $classroom
            ? $classroom->students()->pluck('students.id')
            : $teacher->studentsQuery()->pluck('students.id');

        $sessions = PracticeSession::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->with('lesson:id,title', 'lesson.material:id,language')
            ->get(['id', 'student_id', 'lesson_id', 'language', 'score', 'completed_at']);

        $assignments = $this->assignments($teacher, $classroom);
        $students = $this->students($teacher, $studentIds, $sessions, $assignments);

        $passed = $sessions->filter(fn (PracticeSession $session) => $session->score >= PracticeService::PASS_SCORE);
        $submissions = $assignments->sum('total');
        $completedSubmissions = $assignments->sum('completed');

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => (int) $from->diffInDays($to) + 1,
            ],
            'classroom' => $classroom?->only(['id', 'name']),
            'summary' => [
                'students' => $studentIds->count(),
                'active_students' => $sessions->pluck('student_id')->unique()->count(),
                'sessions' => $sessions->count(),
                'passed' => $passed->count(),
                'pass_rate' => $sessions->count() ? (int) round($passed->count() / $sessions->count() * 100) : 0,
                'avg_score' => round((float) $sessions->avg('score'), 1),
                'letters_mastered' => $passed->map(fn ($session) => $session->student_id.'-'.$session->lesson_id)->unique()->count(),
                'assignments' => $assignments->count(),
                'assignment_completion' => $submissions ? (int) round($completedSubmissions / $submissions * 100) : 0,
            ],
            'daily' => $this->daily($sessions, $from, $to),
            'students' => $students,
            'letters' => $this->letters($sessions),
            'assignments' => $assignments,
        ];
    }

    /**
     * Jumlah latihan per hari dalam periode (hari tanpa latihan tetap tampil).
     *
     * @param  Collection<int, PracticeSession>  $sessions
     * @return array<int, array{date: string, sessions: int, passed: int}>
     */
    private function daily(Collection $sessions, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byDay = $sessions->groupBy(fn (PracticeSession $session) => $session->completed_at->toDateString());

        $days = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $rows = $byDay->get($day->toDateString(), collect());
            $days[] = [
                'date' => $day->toDateString(),
                'sessions' => $rows->count(),
                'passed' => $rows->where('score', '>=', PracticeService::PASS_SCORE)->count(),
            ];
        }

        return $days;
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @param  Collection<int, PracticeSession>  $sessions
     * @param  Collection<int, array<string, mixed>>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    private function students(Teacher $teacher, Collection $studentIds, Collection $sessions, Collection $assignments): Collection
    {
        $bySession = $sessions->groupBy('student_id');

        // Huruf dikuasai sepanjang waktu (bukan hanya periode ini)
        $masteredTotal = PracticeSession::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->where('score', '>=', PracticeService::PASS_SCORE)
            ->groupBy('student_id')
            ->selectRaw('student_id, COUNT(DISTINCT lesson_id) as total')
            ->pluck('total', 'student_id');

        $lastActive = PracticeSession::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->groupBy('student_id')
            ->selectRaw('student_id, MAX(completed_at) as last_at')
            ->pluck('last_at', 'student_id');

        $submissions = AssignmentSubmission::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        $classNames = DB::table('classroom_student as cs')
            ->join('classrooms as c', 'c.id', '=', 'cs.classroom_id')
            ->where('c.teacher_id', $teacher->id)
            ->whereIn('cs.student_id', $studentIds)
            ->get(['cs.student_id', 'c.name'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('name')->implode(', '));

        return DB::table('students as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->whereIn('s.id', $studentIds)
            ->orderBy('u.name')
            ->get(['s.id', 'u.name', 'u.email'])
            ->map(function ($row) use ($bySession, $masteredTotal, $lastActive, $submissions, $classNames) {
                $own = $bySession->get($row->id, collect());
                $passedOwn = $own->filter(fn ($session) => $session->score >= PracticeService::PASS_SCORE);
                $ownSubmissions = $submissions->get($row->id, collect());

                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'classes' => $classNames->get($row->id, ''),
                    'sessions' => $own->count(),
                    'passed' => $passedOwn->count(),
                    'pass_rate' => $own->count() ? (int) round($passedOwn->count() / $own->count() * 100) : 0,
                    'avg_score' => round((float) $own->avg('score'), 1),
                    'new_letters' => $passedOwn->pluck('lesson_id')->unique()->count(),
                    'mastered_total' => (int) ($masteredTotal[$row->id] ?? 0),
                    'assignments_done' => $ownSubmissions->where('status', AssignmentSubmission::STATUS_COMPLETED)->count(),
                    'assignments_total' => $ownSubmissions->count(),
                    'last_active' => isset($lastActive[$row->id]) ? CarbonImmutable::parse($lastActive[$row->id])->toIso8601String() : null,
                    'status' => match (true) {
                        $own->isEmpty() => 'Tidak berlatih',
                        $own->count() < self::LOW_ACTIVITY_SESSIONS => 'Kurang aktif',
                        default => 'Aktif',
                    },
                ];
            })
            ->values();
    }

    /**
     * Huruf tersulit: tingkat lulus terendah dalam periode (min. 2 percobaan).
     *
     * @param  Collection<int, PracticeSession>  $sessions
     * @return Collection<int, array{letter: string, language: string, attempts: int, passed: int, pass_rate: int}>
     */
    private function letters(Collection $sessions): Collection
    {
        return $sessions
            ->groupBy(fn (PracticeSession $session) => strtoupper($session->language).'|'.$session->lesson?->title)
            ->map(function (Collection $rows, string $key) {
                [$language, $letter] = explode('|', $key);
                $passed = $rows->where('score', '>=', PracticeService::PASS_SCORE)->count();

                return [
                    'letter' => $letter,
                    'language' => $language,
                    'attempts' => $rows->count(),
                    'passed' => $passed,
                    'pass_rate' => (int) round($passed / $rows->count() * 100),
                ];
            })
            ->filter(fn (array $row) => $row['attempts'] >= 2)
            ->sortBy([['pass_rate', 'asc'], ['attempts', 'desc']])
            ->take(10)
            ->values();
    }

    /**
     * Rekap tugas guru (opsional satu kelas): penyelesaian & rata-rata skor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function assignments(Teacher $teacher, ?Classroom $classroom): Collection
    {
        return Assignment::query()
            ->where('teacher_id', $teacher->id)
            ->when($classroom, fn ($query) => $query->where('classroom_id', $classroom->id))
            ->with(['lesson:id,title,material_id', 'lesson.material:id,language', 'classroom:id,name', 'submissions:id,assignment_id,status,score'])
            ->latest()
            ->get()
            ->map(function (Assignment $assignment) {
                $completed = $assignment->submissions->where('status', AssignmentSubmission::STATUS_COMPLETED);

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'classroom' => $assignment->classroom?->name ?? 'Arsip',
                    'lesson' => $assignment->lesson?->title,
                    'language' => strtoupper((string) $assignment->lesson?->material?->language),
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'total' => $assignment->submissions->count(),
                    'completed' => $completed->count(),
                    'avg_score' => $completed->isNotEmpty() ? round((float) $completed->avg('score'), 1) : null,
                ];
            });
    }
}
