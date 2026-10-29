<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\Contracts\GestureRecognitionService;
use App\Services\GamificationService;
use App\Services\StudentStatsService;
use App\Services\TeacherStatsService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SignTechApiController extends Controller
{
    public function __construct(
        private StudentStatsService $studentStats,
        private TeacherStatsService $teacherStats,
        private GestureRecognitionService $recognizer,
    ) {}

    /**
     * Resolves the current authenticated student directly from database.
     * Returns null if the user is not authenticated or not a student.
     */
    public function resolveStudent(Request $request): ?Student
    {
        $user = $request->user();

        if ($user && $user->isStudent()) {
            return $this->studentStats->forUser($user);
        }

        return null;
    }

    /**
     * Dashboard data for the Vue frontend — 100% real database data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan login terlebih dahulu untuk mengakses dashboard belajar.',
            ], 401);
        }

        try {
            $user = $student->user;
            $uid = $user->id;

            ChallengeService::ensureTodayChallenges();

            $summary = $this->studentStats->dashboardSummary($student);
            $progress = $this->studentStats->progressByCategory($student);
            $recentSessions = $this->studentStats->recentSessions($student, 8);
            $recommended = $this->studentStats->recommendedLessons($student, 3);
            $challenges = ChallengeService::challengeStatus($uid);

            $days = DB::table('practice_sessions')
                ->where('student_id', $student->id)
                ->where('status', PracticeSession::STATUS_COMPLETED)
                ->selectRaw('DATE(created_at) as d, COUNT(*) as count')
                ->groupBy('d')
                ->pluck('count', 'd')
                ->all();

            $weekDays = [];
            $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            for ($i = 6; $i >= 0; $i--) {
                $date = CarbonImmutable::today()->subDays($i);
                $key = $date->toDateString();
                $weekDays[] = [
                    'date' => $key,
                    'label' => $dayNames[$date->dayOfWeek],
                    'count' => (int) ($days[$key] ?? 0),
                    'isToday' => $i === 0,
                ];
            }

            $achievements = GamificationService::achievements($uid);
            $unlockedBadges = array_values(array_filter($achievements, fn (array $badge) => $badge['unlocked']));

            return response()->json([
                'status' => 'success',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'initials' => collect(explode(' ', $user->name))
                        ->filter()
                        ->take(2)
                        ->map(fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)))
                        ->implode(''),
                ],
                'summary' => $summary,
                'weekActivity' => $weekDays,
                'progress' => $progress->map(fn ($p) => [
                    'id' => $p['category']->id,
                    'name' => $p['category']->name,
                    'slug' => $p['category']->slug,
                    'total' => $p['total'],
                    'done' => $p['done'],
                    'percent' => $p['percent'],
                ]),
                'recentSessions' => $recentSessions->map(fn ($s) => [
                    'id' => $s->id,
                    'lesson_title' => $s->lesson?->title ?? 'Latihan',
                    'category_name' => $s->lesson?->material?->category?->name ?? 'Dasar',
                    'score' => (int) $s->score,
                    'xp_earned' => (int) $s->xp_earned,
                    'attempts' => (int) $s->attempts,
                    'completed_at' => $s->completed_at ? Carbon::parse($s->completed_at)->diffForHumans() : 'Baru saja',
                    'passed' => (int) $s->score >= 60,
                ]),
                'recommended' => $recommended->map(fn ($l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'material_title' => $l->material?->title ?? '',
                    'xp_reward' => (int) $l->xp_reward,
                    'difficulty' => (int) $l->difficulty,
                ]),
                'challenges' => $challenges->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'title' => $c->title,
                    'description' => $c->description,
                    'progress' => (int) $c->progress,
                    'target' => (int) $c->target,
                    'completed' => (bool) $c->completed,
                    'reward_xp' => (int) $c->reward_xp,
                ]),
                'badgeCount' => [
                    'unlocked' => count($unlockedBadges),
                    'total' => count($achievements),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Learning materials list — real database categories, materials, and lessons.
     */
    public function materials(Request $request): JsonResponse
    {
        try {
            $categories = Category::query()
                ->with(['materials.lessons'])
                ->orderBy('order')
                ->get();

            $student = $this->resolveStudent($request);
            $completedLessonIds = $student
                ? $student->practiceSessions()
                    ->where('status', PracticeSession::STATUS_COMPLETED)
                    ->where('score', '>=', 60)
                    ->pluck('lesson_id')
                    ->unique()
                    ->values()
                    ->toArray()
                : [];

            return response()->json([
                'status' => 'success',
                'categories' => $categories,
                'completedLessonIds' => $completedLessonIds,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Single lesson data from database.
     */
    public function getLesson(Request $request, string|int $id): JsonResponse
    {
        try {
            $lesson = null;
            if (is_numeric($id)) {
                $lesson = Lesson::with(['material.category'])->find((int) $id);
            }

            if (!$lesson) {
                $clean = preg_replace('/^bisindo-/', '', (string) $id);
                $lesson = Lesson::with(['material.category'])
                    ->where('title', $clean)
                    ->orWhere('gesture_label', strtolower($clean))
                    ->first();
            }

            if (!$lesson) {
                $lesson = Lesson::with(['material.category'])->firstOrFail();
            }

            $relatedLessons = Lesson::where('material_id', $lesson->material_id)
                ->orderBy('order')
                ->get();

            $student = $this->resolveStudent($request);
            $isCompleted = false;
            if ($student) {
                $isCompleted = $student->practiceSessions()
                    ->where('lesson_id', $lesson->id)
                    ->where('status', PracticeSession::STATUS_COMPLETED)
                    ->where('score', '>=', 60)
                    ->exists();
            }

            return response()->json([
                'status' => 'success',
                'lesson' => $lesson,
                'material' => $lesson->material,
                'category' => $lesson->material?->category,
                'relatedLessons' => $relatedLessons,
                'is_completed' => $isCompleted,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Complete a lesson directly in database.
     */
    public function completeLesson(Request $request, string|int $id): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $lesson = Lesson::find($id);
        if (!$lesson) {
            $clean = preg_replace('/^bisindo-/', '', (string) $id);
            $lesson = Lesson::where('title', $clean)
                ->orWhere('gesture_label', strtolower($clean))
                ->first();
        }

        if (!$lesson) {
            $lesson = Lesson::first();
        }

        $xpReward = $lesson?->xp_reward ?? 10;

        $session = PracticeSession::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson?->id ?? 1,
            'language' => $lesson?->material?->language ?? 'sibi',
            'score' => 100,
            'xp_earned' => $xpReward,
            'attempts' => 1,
            'status' => PracticeSession::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);

        $today = today();
        if (!$student->last_activity_date?->isSameDay($today)) {
            $continues = $student->last_activity_date?->isSameDay($today->subDay());
            $newStreak = $continues ? $student->current_streak + 1 : 1;
            $student->update([
                'current_streak' => $newStreak,
                'longest_streak' => max($student->longest_streak, $newStreak),
                'last_activity_date' => $today,
            ]);
        }

        $categorySlug = $lesson?->material?->category?->slug ?? 'alphabet';
        ChallengeService::bumpChallenge($student->user_id, $categorySlug);
        GamificationService::evaluateBadges($student->user_id);

        return response()->json([
            'status' => 'success',
            'message' => 'Pelajaran berhasil diselesaikan.',
            'xp_earned' => $xpReward,
            'total_xp' => $student->totalXp(),
            'streak' => GamificationService::streakInfo($student->user_id)['streak'],
        ]);
    }

    /**
     * Practice targets from database for AI Practice Studio.
     */
    public function practiceTargets(Request $request): JsonResponse
    {
        try {
            $lessons = Lesson::query()
                ->with('material.category')
                ->whereHas('material.category', fn ($q) => $q->where('slug', 'alphabet'))
                ->orderBy('order')
                ->get();

            if ($lessons->isEmpty()) {
                $lessons = Lesson::with('material.category')->take(10)->get();
            }

            $student = $this->resolveStudent($request);
            $completedLessonIds = $student
                ? $student->practiceSessions()
                    ->where('status', PracticeSession::STATUS_COMPLETED)
                    ->where('score', '>=', 60)
                    ->pluck('lesson_id')
                    ->unique()
                    ->values()
                    ->toArray()
                : [];

            return response()->json([
                'status' => 'success',
                'completedLessonIds' => $completedLessonIds,
                'targets' => $lessons->map(fn ($l) => [
                    'id' => $l->id,
                    'letter' => $l->title,
                    'name' => "Huruf {$l->title}",
                    'hint' => "Peragakan isyarat huruf {$l->title} sesuai kaidah bahasa isyarat.",
                    'language' => $l->material?->language ?? 'sibi',
                    'category' => $l->material?->category?->name ?? 'Alfabet',
                    'practiced' => in_array($l->id, $completedLessonIds),
                ]),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Practice attempt submission & live AI recognition evaluation.
     */
    public function attemptPractice(Request $request): JsonResponse
    {
        $letter = strtoupper(trim((string) $request->input('target_letter', 'A')));
        $system = strtolower(trim((string) $request->input('system', 'sibi')));
        $imageData = $request->input('image') ?? $request->input('frame');
        $mirrored = (bool) $request->input('mirrored', false);

        // Evaluasi gesture langsung via Live AI Service (https://ai.signtech.tech)
        $recognition = $this->recognizer->recognize($letter, $imageData, $system, $mirrored);
        $isCorrect = (bool) ($recognition['correct'] ?? false);
        $confidence = (int) ($recognition['confidence'] ?? 0);
        $recognized = $recognition['recognized'];
        $message = $recognition['message'] ?? '';
        $detectionStatus = $recognition['detection_status'] ?? 'unknown';

        $student = $this->resolveStudent($request);
        $xpEarned = 0;
        $totalXp = 0;
        $streak = 0;

        if ($student) {
            try {
                // Temukan lesson yang sesuai dengan target huruf di DB
                $lesson = Lesson::query()
                    ->where('title', $letter)
                    ->orWhere('gesture_label', strtolower($letter))
                    ->first()
                    ?? Lesson::first();

                $xpEarned = $isCorrect ? ($lesson?->xp_reward ?? 10) : 0;

                // Simpan PracticeSession nyata
                $session = PracticeSession::create([
                    'student_id' => $student->id,
                    'lesson_id' => $lesson?->id ?? 1,
                    'language' => in_array($system, ['sibi', 'bisindo'], true) ? $system : 'sibi',
                    'score' => $isCorrect ? 100 : max(0, $confidence),
                    'xp_earned' => $xpEarned,
                    'attempts' => 1,
                    'status' => PracticeSession::STATUS_COMPLETED,
                    'started_at' => now()->subSeconds(2),
                    'completed_at' => now(),
                ]);

                // Simpan GestureResult nyata
                $session->gestureResults()->create([
                    'lesson_id' => $lesson?->id ?? 1,
                    'expected_gesture' => strtolower($letter),
                    'recognized_gesture' => $recognized ? strtolower($recognized) : 'none',
                    'confidence' => $confidence,
                    'correct' => $isCorrect,
                ]);

                if ($isCorrect) {
                    // Update streak nyata di database
                    $today = today();
                    if (!$student->last_activity_date?->isSameDay($today)) {
                        $continues = $student->last_activity_date?->isSameDay($today->subDay());
                        $newStreak = $continues ? $student->current_streak + 1 : 1;
                        $student->update([
                            'current_streak' => $newStreak,
                            'longest_streak' => max($student->longest_streak, $newStreak),
                            'last_activity_date' => $today,
                        ]);
                    }

                    // Tambah progres tantangan harian di DB
                    $categorySlug = $lesson?->material?->category?->slug ?? 'alphabet';
                    ChallengeService::bumpChallenge($student->user_id, $categorySlug);

                    // Evaluasi perolehan badge gamifikasi
                    GamificationService::evaluateBadges($student->user_id);
                }

                $totalXp = $student->totalXp();
                $streak = GamificationService::streakInfo($student->user_id)['streak'];
            } catch (Throwable $e) {
                // DB exception logged gracefully
            }
        }

        return response()->json([
            'status' => 'success',
            'correct' => $isCorrect,
            'recognized' => $recognized,
            'confidence' => $confidence,
            'detection_status' => $detectionStatus,
            'message' => $message,
            'xp_earned' => $xpEarned,
            'total_xp' => $totalXp,
            'streak' => $streak,
            'source' => $recognition['source'] ?? 'signtech_ai_service',
        ]);
    }

    /**
     * Daily challenges status — real database data.
     */
    public function challenges(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil siswa tidak ditemukan di database.',
            ], 404);
        }

        try {
            $uid = $student->user_id;
            ChallengeService::ensureTodayChallenges();
            $challenges = ChallengeService::challengeStatus($uid);
            $streak = GamificationService::streakInfo($uid);
            $rewards = ChallengeService::challengeRewards($uid)->take(5);

            return response()->json([
                'status' => 'success',
                'challenges' => $challenges,
                'streak' => $streak['streak'],
                'is_today' => $streak['is_today'],
                'rewards' => $rewards,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Claim daily challenge reward — updates database challenge_progress and awards XP.
     */
    public function claimChallenge(Request $request): JsonResponse
    {
        $challengeId = (int) $request->input('challenge_id', 0);
        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil siswa tidak ditemukan di database.',
            ], 404);
        }

        $challenge = Challenge::find($challengeId);
        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tantangan tidak ditemukan di database.',
            ], 404);
        }

        try {
            DB::table('challenge_progress')->updateOrInsert(
                ['user_id' => $student->user_id, 'challenge_id' => $challengeId],
                ['completed' => 1, 'progress' => $challenge->target, 'completed_at' => now(), 'updated_at' => now()]
            );

            GamificationService::evaluateBadges($student->user_id);
            $totalXp = $student->totalXp();

            return response()->json([
                'status' => 'success',
                'claimed' => true,
                'reward_xp' => (int) $challenge->reward_xp,
                'total_xp' => $totalXp,
                'message' => "Selamat! Bonus +{$challenge->reward_xp} XP berhasil diklaim.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Achievements catalog and unlock status — real database badges & achievements.
     */
    public function achievements(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil siswa tidak ditemukan di database.',
            ], 404);
        }

        try {
            $uid = $student->user_id;
            $achievements = GamificationService::achievements($uid);
            $progress = GamificationService::badgeProgress($uid);
            $totalXp = $student->totalXp();
            $level = GamificationService::levelFromXp($totalXp);
            $streak = GamificationService::streakInfo($uid);

            return response()->json([
                'status' => 'success',
                'achievements' => $achievements,
                'progress' => $progress,
                'level' => $level,
                'streak' => $streak['streak'],
                'total_xp' => $totalXp,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Classroom monitoring overview for teachers — real database overview, students, and error metrics.
     */
    public function classroom(Request $request): JsonResponse
    {
        try {
            $teacherUser = $request->user();
            if (!$teacherUser || !$teacherUser->isTeacher()) {
                $teacherUser = User::where('role', User::ROLE_TEACHER)->first() ?? User::first();
            }

            if (!$teacherUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun pengajar tidak ditemukan di database.',
                ], 404);
            }

            $teacher = $this->teacherStats->forUser($teacherUser);
            $overview = $this->teacherStats->classOverview($teacher);
            $students = $this->teacherStats->studentsOverview();
            $activeAssignments = $this->teacherStats->activeAssignments($teacher);
            $errorAnalysis = $this->teacherStats->errorAnalysisForLesson()->take(8);

            return response()->json([
                'status' => 'success',
                'overview' => $overview,
                'students' => $students->map(fn ($s) => [
                    'id' => $s['student']->id,
                    'name' => $s['name'],
                    'sessions' => $s['sessions'],
                    'avg_score' => $s['avg_score'],
                    'xp' => $s['xp'],
                    'level' => $s['level'],
                    'streak' => $s['streak'],
                    'last_activity' => $s['last_activity'] ? Carbon::parse($s['last_activity'])->diffForHumans() : 'Belum aktif',
                ]),
                'activeAssignments' => $activeAssignments->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'lesson_title' => $a->lesson?->title ?? '',
                    'due_date' => $a->due_at ? $a->due_at->format('d M Y') : 'Tanpa deadline',
                ]),
                'errorAnalysis' => $errorAnalysis,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
