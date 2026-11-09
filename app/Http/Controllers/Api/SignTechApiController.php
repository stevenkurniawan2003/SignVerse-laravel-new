<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Services\AlphabetLessonService;
use App\Services\ChallengeService;
use App\Services\Contracts\GestureRecognitionService;
use App\Services\GamificationService;
use App\Services\PracticeService;
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
        private PracticeService $practice,
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

        if (! $student) {
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
                'levels' => GamificationService::levels(),
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
                'xpHistory' => $this->studentStats->xpHistory($student, 10)->map(fn (array $entry) => [
                    'id' => $entry['id'],
                    'source' => $entry['source'],
                    'title' => $entry['title'],
                    'detail' => $entry['detail'],
                    'xp' => $entry['xp'],
                    'earned_at' => $entry['earned_at'] ? Carbon::parse($entry['earned_at'])->diffForHumans() : 'Baru saja',
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
                'leaderboard' => GamificationService::rankings($uid),
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Learning materials list — real database categories, materials, and lessons.
     */
    public function materials(Request $request): JsonResponse
    {
        try {
            AlphabetLessonService::ensureComplete();

            $language = $request->query('language');
            $categories = Category::query()
                ->with(['materials' => function ($q) use ($language) {
                    if ($language) {
                        $q->where('language', $language);
                    }
                    $q->orderBy('order')->orderBy('id')
                        ->with(['lessons' => fn ($lessons) => $lessons->orderBy('order')->orderBy('id')]);
                }])
                ->when($language, fn ($q) => $q->whereHas('materials', fn ($m) => $m->where('language', $language)))
                ->orderBy('order')
                ->get();

            foreach ($categories as $category) {
                foreach ($category->materials as $material) {
                    foreach ($material->lessons as $lesson) {
                        $lesson->setAttribute('ai_supported', in_array($category->slug, PracticeService::AI_SUPPORTED_CATEGORIES, true)
                            && ($category->slug === 'alphabet' || PracticeService::spelledLetters($lesson) !== []));
                    }
                }
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
                'categories' => $categories,
                'completedLessonIds' => $completedLessonIds,
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e);
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

            if (! $lesson) {
                $clean = preg_replace('/^bisindo-/', '', (string) $id);
                $lesson = Lesson::with(['material.category'])
                    ->where('title', $clean)
                    ->orWhere('gesture_label', strtolower($clean))
                    ->first();
            }

            if (! $lesson) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pelajaran tidak ditemukan.',
                ], 404);
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
                'ai_supported' => PracticeService::isAiSupported($lesson),
                'spelling' => $student ? $this->practice->spellingProgress($student, $lesson) : null,
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Status penyelesaian lesson. Lesson hanya dianggap selesai bila siswa
     * sudah lulus peragaan kamera (dinilai AI lewat attemptPractice) —
     * endpoint ini tidak pernah membuat skor/XP sendiri.
     */
    public function completeLesson(Request $request, string|int $id): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $lesson = Lesson::with('material')->find($id);
        if (! $lesson) {
            return response()->json(['status' => 'error', 'message' => 'Pelajaran tidak ditemukan.'], 404);
        }

        $bestSession = $student->practiceSessions()
            ->where('lesson_id', $lesson->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->where('score', '>=', PracticeService::PASS_SCORE)
            ->orderByDesc('score')
            ->first();

        if (! $bestSession) {
            return response()->json([
                'status' => 'error',
                'completed' => false,
                'message' => "Peragakan isyarat {$lesson->title} di depan kamera sampai dinyatakan benar oleh AI untuk menyelesaikan pelajaran ini.",
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'completed' => true,
            'message' => 'Pelajaran berhasil diselesaikan.',
            'best_score' => (int) $bestSession->score,
            'total_xp' => $student->totalXp(),
            'streak' => (int) $student->current_streak,
        ]);
    }

    /**
     * Practice targets from database for AI Practice Studio.
     */
    public function practiceTargets(Request $request): JsonResponse
    {
        try {
            AlphabetLessonService::ensureComplete();

            // Satu target per huruf per sistem isyarat, selalu urut A–Z
            // (frontend memfilter per sistem).
            $lessons = Lesson::query()
                ->select('lessons.*')
                ->with('material.category')
                ->join('materials', 'materials.id', '=', 'lessons.material_id')
                ->join('categories', 'categories.id', '=', 'materials.category_id')
                ->where('categories.slug', 'alphabet')
                ->orderBy('lessons.id')
                ->get()
                ->filter(fn (Lesson $l) => preg_match('/^[A-Z]$/', strtoupper(trim($l->title))) === 1)
                ->unique(fn (Lesson $l) => $l->material->language.'|'.strtoupper(trim($l->title)))
                ->sortBy(fn (Lesson $l) => strtoupper(trim($l->title)))
                ->values();

            $student = $this->resolveStudent($request);
            $completedLessonIds = $student
                ? $student->practiceSessions()
                    ->where('status', PracticeSession::STATUS_COMPLETED)
                    ->where('score', '>=', PracticeService::PASS_SCORE)
                    ->pluck('lesson_id')
                    ->unique()
                    ->values()
                    ->toArray()
                : [];

            return response()->json([
                'status' => 'success',
                'completedLessonIds' => $completedLessonIds,
                'targets' => $lessons->map(function (Lesson $l) use ($completedLessonIds) {
                    $isNumber = $l->material?->category?->slug === 'number';
                    $kind = $isNumber ? 'Angka' : 'Huruf';

                    return [
                        'id' => $l->id,
                        'letter' => $l->title,
                        'name' => "{$kind} {$l->title}",
                        'hint' => "Peragakan isyarat {$kind} {$l->title}, tahan posisi tangan sampai foto diambil.",
                        'language' => $l->material?->language ?? 'sibi',
                        'category' => $l->material?->category?->name ?? 'Alfabet',
                        'category_slug' => $l->material?->category?->slug ?? 'alphabet',
                        'xp_reward' => (int) $l->xp_reward,
                        'practiced' => in_array($l->id, $completedLessonIds),
                    ];
                }),
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Practice attempt submission & live AI recognition evaluation.
     */
    public function attemptPractice(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'Latihan hanya tersedia untuk siswa.'], 403);
        }

        $request->validate([
            'lesson_id' => ['nullable', 'integer'],
            'target_letter' => ['nullable', 'string', 'max:150'],
            'system' => ['nullable', 'string', 'in:sibi,bisindo'],
            'image' => ['nullable', 'string', 'max:8192000'],
            'frame' => ['nullable', 'string', 'max:8192000'],
            'progress_token' => ['nullable', 'string', 'max:100'],
        ]);

        $lesson = $this->resolvePracticeLesson($request);
        if (! $lesson) {
            return response()->json([
                'status' => 'error',
                'message' => 'Target latihan tidak ditemukan untuk sistem isyarat yang dipilih.',
            ], 422);
        }

        if (! PracticeService::isAiSupported($lesson)) {
            return response()->json([
                'status' => 'error',
                'message' => "Isyarat {$lesson->title} belum bisa dinilai AI. Latihan mendukung alfabet serta ejaan kata/frasa yang hanya berisi huruf A-Z dan spasi.",
            ], 422);
        }

        $imageData = $request->input('image') ?? $request->input('frame');
        $mirrored = $request->boolean('mirrored');
        $spelling = [];
        $expected = $lesson->title;

        if (PracticeService::isSpellingLesson($lesson)) {
            $attempt = $this->practice->attemptSpelling($student, $lesson, $imageData, $mirrored, $request->input('progress_token'));
            $spelling = $attempt['spelling'] ?? [];

            if ($attempt['status_code'] !== 200) {
                return response()->json([
                    'status' => 'error',
                    'lesson_id' => $lesson->id,
                    'correct' => false,
                    'evaluated' => false,
                    'message' => $attempt['message'],
                    ...$spelling,
                ], $attempt['status_code']);
            }

            $recognition = $attempt['recognition'];
            $outcome = $attempt['outcome'];
            $expected = $attempt['attempted_letter'];
            $spelling['attempted_letter'] = $expected;
            $spelling['attempted_index'] = $attempt['attempted_index'];
        } else {
            $recognition = $this->recognizer->recognize($expected, $imageData, $lesson->material->language, $mirrored);
            $outcome = $this->practice->recordVerifiedAttempt($student, $lesson, $recognition);
        }

        $confidence = (int) ($recognition['confidence'] ?? 0);
        $recognized = $recognition['recognized'] ?? null;
        $message = $recognition['message'] ?? '';

        if ($recognized !== null && strtoupper(trim((string) $recognized)) === strtoupper($expected) && $confidence < PracticeService::PASS_SCORE) {
            $message = "Bentuk tangan mirip isyarat {$expected}, tetapi belum cukup jelas (akurasi {$confidence}%, minimal "
                .PracticeService::PASS_SCORE.'%). Tahan posisi tangan lebih stabil lalu coba lagi.';
        }

        $passed = $outcome['passed'] ?? ($recognized !== null && ($recognition['correct'] ?? false) && $confidence >= PracticeService::PASS_SCORE);

        if ($passed && $spelling !== []) {
            $message = $spelling['word_completed']
                ? 'Ejaan '.$spelling['word'].' berhasil diselesaikan.'
                : "Huruf {$expected} benar. Lanjutkan ke huruf {$spelling['current_letter']}.";
        }

        return response()->json([
            'status' => 'success',
            'lesson_id' => $lesson->id,
            'correct' => $passed,
            'evaluated' => $recognized !== null,
            'recognized' => $recognized,
            'confidence' => $confidence,
            'pass_score' => PracticeService::PASS_SCORE,
            'detection_status' => $recognition['detection_status'] ?? 'unknown',
            'message' => $message,
            'score' => $outcome['score'] ?? 0,
            'xp_earned' => $outcome['xp_earned'] ?? 0,
            'already_mastered' => $outcome['already_mastered'] ?? false,
            'total_xp' => $outcome['total_xp'] ?? $student->totalXp(),
            'level' => $outcome['level'] ?? null,
            'leveled_up' => $outcome['leveled_up'] ?? false,
            'new_badges' => $outcome['new_badges'] ?? [],
            'challenge_completed' => $outcome['challenge_completed'] ?? false,
            'assignments_completed' => $outcome['assignments_completed'] ?? [],
            'streak' => $outcome['streak'] ?? 0,
            'source' => $recognition['source'] ?? 'signtech_ai_service',
            ...$spelling,
        ]);
    }

    /**
     * Lesson target latihan: utamakan lesson_id, atau cocokkan judul
     * (huruf/angka) pada sistem isyarat yang dipilih — tanpa fallback ke
     * lesson lain agar penilaian tidak salah sasaran.
     */
    private function resolvePracticeLesson(Request $request): ?Lesson
    {
        if ($request->filled('lesson_id')) {
            return Lesson::with('material.category')->find((int) $request->input('lesson_id'));
        }

        $title = strtoupper(trim((string) $request->input('target_letter', '')));
        $system = strtolower(trim((string) $request->input('system', 'sibi')));
        if ($title === '' || ! in_array($system, [PracticeSession::LANGUAGE_SIBI, PracticeSession::LANGUAGE_BISINDO], true)) {
            return null;
        }

        return Lesson::with('material.category')
            ->where('title', $title)
            ->whereHas('material', fn ($q) => $q->where('language', $system))
            ->whereHas('material.category', fn ($q) => $q->where('slug', 'alphabet'))
            ->first();
    }

    /**
     * Daily challenges status — real database data.
     */
    public function challenges(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
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
                'title' => 'Challenge Harian',
                'streak_label' => 'Streak Kamu Saat Ini',
                'challenges' => $challenges,
                'streak' => $streak['streak'],
                'is_today' => $streak['is_today'],
                'rewards' => $rewards,
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Claim daily challenge reward — updates database challenge_progress and awards XP.
     */
    public function claimChallenge(Request $request): JsonResponse
    {
        $challengeId = (int) $request->input('challenge_id', 0);
        $student = $this->resolveStudent($request);

        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil siswa tidak ditemukan di database.',
            ], 404);
        }

        $challenge = Challenge::find($challengeId);
        if (! $challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tantangan tidak ditemukan di database.',
            ], 404);
        }

        $progress = DB::table('challenge_progress')
            ->where('user_id', $student->user_id)
            ->where('challenge_id', $challengeId)
            ->first();

        if ((int) ($progress->progress ?? 0) < (int) $challenge->target) {
            $remaining = (int) $challenge->target - (int) ($progress->progress ?? 0);

            return response()->json([
                'status' => 'error',
                'claimed' => false,
                'message' => "Tantangan belum selesai. Peragakan {$remaining} isyarat lagi dengan benar untuk mendapatkan bonus XP.",
            ], 422);
        }

        try {
            DB::table('challenge_progress')
                ->where('user_id', $student->user_id)
                ->where('challenge_id', $challengeId)
                ->where('completed', 0)
                ->update(['completed' => 1, 'completed_at' => now()]);

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
            return $this->serverError($e);
        }
    }

    /**
     * Leaderboard XP siswa — di-poll dashboard setiap beberapa detik.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil siswa tidak ditemukan di database.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            ...GamificationService::rankings($student->user_id),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Achievements catalog and unlock status — real database badges & achievements.
     */
    public function achievements(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
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
                'title' => 'Pencapaian & Leaderboard',
                'leaderboard_title' => 'Leaderboard',
                'achievements' => $achievements,
                'progress' => $progress,
                'level' => $level,
                'streak' => $streak['streak'],
                'total_xp' => $totalXp,
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Catat error ke log dan kirim pesan umum: detail teknis (mis. query SQL)
     * tidak boleh sampai ke pengguna.
     */
    private function serverError(Throwable $e): JsonResponse
    {
        report($e);

        return response()->json([
            'status' => 'error',
            'message' => 'Terjadi kesalahan pada server. Coba lagi beberapa saat lagi.',
        ], 500);
    }

    /**
     * Classroom monitoring overview for teachers — real database overview, students, and error metrics.
     */
    public function classroom(Request $request): JsonResponse
    {
        $teacherUser = $request->user();
        if (! $teacherUser || ! $teacherUser->isTeacher()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Halaman ini hanya untuk guru.',
            ], 403);
        }

        try {
            $teacher = $this->teacherStats->forUser($teacherUser);
            $teacherOverview = $this->teacherStats->teacherOverview($teacher);
            $overview = [...$teacherOverview, 'avg_class_score' => $teacherOverview['avg_score']];
            $students = $this->teacherStats->studentsOverview($teacher);
            $activeAssignments = $this->teacherStats->activeAssignments($teacher);
            $errorAnalysis = $this->teacherStats->errorAnalysisForLesson(teacher: $teacher)->take(8);

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
            return $this->serverError($e);
        }
    }
}
