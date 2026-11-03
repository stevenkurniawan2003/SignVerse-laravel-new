<?php

namespace App\Services;

use App\Models\GestureResult;
use App\Models\Lesson;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Services\Contracts\GestureRecognitionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle sesi latihan (phase 4):
 * start/resume → attempt per gesture → complete (skor + XP).
 *
 * Aturan penilaian (setara project lama):
 *   - skor = persentase gesture benar dari seluruh attempt (0-100)
 *   - lulus  (>= 60) → XP penuh lesson, status completed
 *   - belum lulus → XP 0, sesi tetap completed (bisa diulang kapan saja)
 *   - satu sesi aktif (in_progress) per student; resume, bukan duplikat
 *
 * Setelah sesi selesai, gamifikasi dijalankan:
 * evaluasi badge → bump challenge harian per kategori.
 */
class PracticeService
{
    public const PASS_SCORE = 60;

    /**
     * Kategori yang bisa dinilai AI Service. Model hanya mengenali pose huruf
     * alfabet statis (lihat docs/API_CONTRACT.md di repo sibi-bisindo-mediapipe);
     * isyarat angka belum punya kelas sehingga tidak bisa diverifikasi.
     */
    public const AI_SUPPORTED_CATEGORIES = ['alphabet'];

    public static function isAiSupported(Lesson $lesson): bool
    {
        return in_array($lesson->material?->category?->slug, self::AI_SUPPORTED_CATEGORIES, true);
    }

    public function __construct(
        private GestureRecognitionService $recognizer,
        private AssignmentService $assignments,
    ) {}

    /**
     * Mulai sesi baru untuk lesson, atau lanjutkan sesi in_progress yang ada.
     */
    public function startSession(Student $student, Lesson $lesson): PracticeSession
    {
        return PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->where('status', PracticeSession::STATUS_IN_PROGRESS)
            ->first()
            ?? PracticeSession::create([
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
                'language' => $lesson->material->language,
                'status' => PracticeSession::STATUS_IN_PROGRESS,
                'attempts' => 0,
            ]);
    }

    /**
     * Satu attempt latihan: jalankan recognizer, simpan gesture result.
     *
     * @return array{result: GestureResult, session: PracticeSession}
     */
    public function attempt(PracticeSession $session, ?string $imageData = null): array
    {
        $lesson = $session->lesson;

        $recognition = $this->recognizer->recognize($lesson->gesture_label, $imageData);

        $result = DB::transaction(function () use ($session, $lesson, $recognition) {
            $gestureResult = GestureResult::create([
                'practice_session_id' => $session->id,
                'lesson_id' => $lesson->id,
                'expected_gesture' => $lesson->gesture_label,
                'recognized_gesture' => $recognition['recognized'],
                'confidence' => $recognition['confidence'],
                'correct' => $recognition['correct'],
            ]);

            $session->increment('attempts');

            return $gestureResult;
        });

        return ['result' => $result, 'session' => $session->refresh()];
    }

    /**
     * Akhiri sesi: hitung skor dari gesture results, tentukan XP, update
     * streak student lalu evaluasi gamifikasi. Return sesi final + lulus.
     *
     * @return array{session: PracticeSession, passed: bool, score: int, xp_earned: int, new_badges: string[]}
     */
    public function complete(PracticeSession $session): array
    {
        if ($session->status !== PracticeSession::STATUS_IN_PROGRESS) {
            // Sesi sudah final — kembalikan hasilnya apa adanya (idempoten).
            return [
                'session' => $session,
                'passed' => $session->score >= self::PASS_SCORE,
                'score' => (int) $session->score,
                'xp_earned' => (int) $session->xp_earned,
                'new_badges' => [],
            ];
        }

        return DB::transaction(function () use ($session) {
            $total = $session->gestureResults()->count();
            $correct = $session->gestureResults()->where('correct', true)->count();

            $score = $total > 0 ? (int) round($correct / $total * 100) : 0;
            $passed = $score >= self::PASS_SCORE;
            $xp = $passed && ! $this->hasMastered($session->student, $session->lesson) ? $session->lesson->xp_reward : 0;
            $alreadyPassedToday = $this->hasPassedToday($session->student, $session->lesson);

            $session->forceFill([
                'status' => PracticeSession::STATUS_COMPLETED,
                'score' => $score,
                'xp_earned' => $xp,
                'completed_at' => now(),
            ])->save();

            $this->updateStreak($session->student);

            // Gamifikasi:
            // evaluasi badge lalu tambahkan progres challenge harian.
            $userId = $session->student->user_id;
            $badgesBefore = GamificationService::unlockedKeys($userId);
            GamificationService::evaluateBadges($userId);
            $newBadges = GamificationService::badgeNames(array_values(array_diff(
                GamificationService::unlockedKeys($userId),
                $badgesBefore,
            )));

            if ($passed && ! $alreadyPassedToday && $session->lesson->material) {
                ChallengeService::bumpChallenge($userId, $session->lesson->material->category->slug);
            }

            return [
                'session' => $session,
                'passed' => $passed,
                'score' => $score,
                'xp_earned' => $xp,
                'new_badges' => $newBadges,
            ];
        });
    }

    /**
     * Catat satu peragaan isyarat yang sudah dievaluasi AI, lalu jalankan
     * gamifikasi (XP, streak, challenge harian, badge, level).
     *
     * Aturan:
     *   - kamera tidak menangkap tangan / AI tidak terjangkau → tidak dicatat
     *   - lulus bila label AI sama dengan target DAN confidence >= PASS_SCORE
     *   - skor = confidence bila label benar, 0 bila label salah
     *   - XP pelajaran hanya diberikan saat pertama kali lulus (anti-farming)
     *   - satu peragaan hanya memberi satu jenis XP: bila peragaan menyelesaikan
     *     tugas guru, XP pelajaran tidak ikut dihitung (hanya XP tugas)
     *   - xp_earned = total XP yang didapat dari peragaan ini (pelajaran atau tugas)
     *   - challenge harian menghitung huruf berbeda: huruf yang sama hanya sekali
     *     per hari; peragaan ulang tetap dihitung untuk badge
     *
     * @param  array{recognized: ?string, confidence: int, correct: bool}  $recognition
     * @return array{recorded: bool, passed: bool, score: int, xp_earned: int, already_mastered: bool, total_xp: int, level: array<string, mixed>, leveled_up: bool, new_badges: string[], challenge_completed: bool, assignments_completed: array<int, array{title: string, xp_earned: int}>, streak: int}
     */
    public function recordVerifiedAttempt(Student $student, Lesson $lesson, array $recognition): array
    {
        $userId = $student->user_id;
        $xpBefore = $student->totalXp();
        $levelBefore = GamificationService::levelFromXp($xpBefore);

        if (($recognition['recognized'] ?? null) === null) {
            return [
                'recorded' => false,
                'passed' => false,
                'score' => 0,
                'xp_earned' => 0,
                'already_mastered' => $this->hasMastered($student, $lesson),
                'total_xp' => $xpBefore,
                'level' => $levelBefore,
                'leveled_up' => false,
                'new_badges' => [],
                'challenge_completed' => false,
                'assignments_completed' => [],
                'streak' => (int) $student->current_streak,
            ];
        }

        $confidence = (int) ($recognition['confidence'] ?? 0);
        $isCorrectLabel = (bool) ($recognition['correct'] ?? false);
        $passed = $isCorrectLabel && $confidence >= self::PASS_SCORE;
        $score = $isCorrectLabel ? $confidence : 0;
        $alreadyMastered = $this->hasMastered($student, $lesson);
        $xp = $passed && ! $alreadyMastered ? (int) $lesson->xp_reward : 0;
        $alreadyPassedToday = $this->hasPassedToday($student, $lesson);

        return DB::transaction(function () use ($student, $lesson, $recognition, $userId, $levelBefore, $passed, $score, $alreadyMastered, $xp, $alreadyPassedToday): array {
            $session = PracticeSession::create([
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
                'language' => $lesson->material->language,
                'status' => PracticeSession::STATUS_COMPLETED,
                'score' => $score,
                'xp_earned' => $xp,
                'attempts' => 1,
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            $session->gestureResults()->create([
                'lesson_id' => $lesson->id,
                'expected_gesture' => $lesson->gesture_label,
                'recognized_gesture' => strtolower((string) $recognition['recognized']),
                'confidence' => (int) ($recognition['confidence'] ?? 0),
                'correct' => $passed,
            ]);

            $newBadges = [];
            $challengeCompleted = false;
            $assignmentsCompleted = [];

            if ($passed) {
                $this->updateStreak($student);

                // Tugas guru yang menargetkan lesson ini ikut selesai otomatis.
                $assignmentsCompleted = $this->assignments->completeFromPractice($student, $session);

                // Satu peragaan hanya memberi satu jenis XP: bila peragaan ini
                // menyelesaikan tugas guru, yang dihitung hanya XP tugas tersebut.
                if ($assignmentsCompleted !== [] && $xp > 0) {
                    $xp = 0;
                    $session->forceFill(['xp_earned' => 0])->save();
                }

                // Siswa bisa langsung berlatih tanpa membuka dashboard lebih dulu hari ini.
                ChallengeService::ensureTodayChallenges();
                $challengeCompleted = ! $alreadyPassedToday
                    && ChallengeService::bumpChallenge($userId, $lesson->material->category->slug);

                $badgesBefore = GamificationService::unlockedKeys($userId);
                GamificationService::evaluateBadges($userId);
                $newBadges = GamificationService::badgeNames(array_values(array_diff(
                    GamificationService::unlockedKeys($userId),
                    $badgesBefore,
                )));
            }

            $totalXp = $student->totalXp();
            $level = GamificationService::levelFromXp($totalXp);

            return [
                'recorded' => true,
                'passed' => $passed,
                'score' => $score,
                'xp_earned' => $xp + array_sum(array_column($assignmentsCompleted, 'xp_earned')),
                'already_mastered' => $alreadyMastered,
                'total_xp' => $totalXp,
                'level' => $level,
                'leveled_up' => $level['name'] !== $levelBefore['name'],
                'new_badges' => $newBadges,
                'challenge_completed' => $challengeCompleted,
                'assignments_completed' => $assignmentsCompleted,
                'streak' => (int) $student->refresh()->current_streak,
            ];
        });
    }

    /**
     * Apakah student sudah pernah lulus peragaan lesson ini.
     */
    public function hasMastered(Student $student, Lesson $lesson): bool
    {
        return $student->practiceSessions()
            ->where('lesson_id', $lesson->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->where('score', '>=', self::PASS_SCORE)
            ->exists();
    }

    /**
     * Apakah student sudah lulus peragaan lesson ini pada hari ini.
     */
    private function hasPassedToday(Student $student, Lesson $lesson): bool
    {
        return $student->practiceSessions()
            ->where('lesson_id', $lesson->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->where('score', '>=', self::PASS_SCORE)
            ->whereDate('completed_at', CarbonImmutable::today()->toDateString())
            ->exists();
    }

    /**
     * Ambil sesi in_progress milik student, atau gagal 404.
     */
    public function findActiveSession(Student $student, int $sessionId): PracticeSession
    {
        $session = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            throw (new ModelNotFoundException)->setModel(PracticeSession::class, [$sessionId]);
        }

        return $session;
    }

    /**
     * Streak: naik bila latihan hari berturut-turut, reset bila bolong,
     * rekor terpanjang ikut disimpan.
     */
    private function updateStreak(Student $student): void
    {
        $today = CarbonImmutable::today();

        if ($student->last_activity_date?->isSameDay($today)) {
            return; // Sudah dihitung hari ini
        }

        $continues = $student->last_activity_date?->isSameDay($today->subDay());

        $current = $continues ? $student->current_streak + 1 : 1;

        $student->forceFill([
            'current_streak' => $current,
            'longest_streak' => max($student->longest_streak, $current),
            'last_activity_date' => $today,
        ])->save();
    }
}
