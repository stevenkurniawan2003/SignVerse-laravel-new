<?php

namespace App\Services;

use App\Models\PracticeSession;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membersihkan data latihan yang tercatat oleh logika lama agar sesuai aturan
 * penilaian sekarang (PracticeService::recordVerifiedAttempt):
 *
 *   - sesi tanpa gesture result (lesson "diselesaikan" tanpa peragaan) dihapus
 *   - gesture result tanpa deteksi AI ("none") / label recognizer mock dihapus
 *   - skor = confidence terbaik dengan label benar, lulus bila >= PASS_SCORE
 *   - XP hanya untuk kelulusan pertama per lesson
 *   - streak, progres challenge harian, dan badge dihitung ulang
 */
class PracticeDataCleanupService
{
    /** Label keluaran MockGestureRecognitionService — bukan hasil AI nyata. */
    private const MOCK_LABELS = ['salah_satu', 'salah_dua', 'salah_tiga'];

    /** Akun demo dari DatabaseSeeder; datanya sengaja dibiarkan. */
    public const DEMO_EMAIL_DOMAIN = '@signteach.id';

    /**
     * @return array<int, array{email: string, sessions_deleted: int, sessions_rescored: int, xp_before: int, xp_after: int, streak_before: int, streak_after: int, badges_removed: string[]}>
     */
    public function clean(bool $includeDemo = false): array
    {
        $students = Student::query()
            ->with('user:id,email')
            ->whereHas('practiceSessions')
            ->when(! $includeDemo, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('email', 'not like', '%'.self::DEMO_EMAIL_DOMAIN)))
            ->get();

        return $students->map(fn (Student $student) => $this->cleanStudent($student))->values()->all();
    }

    /**
     * @return array{email: string, sessions_deleted: int, sessions_rescored: int, xp_before: int, xp_after: int, streak_before: int, streak_after: int, badges_removed: string[]}
     */
    private function cleanStudent(Student $student): array
    {
        $userId = $student->user_id;
        $xpBefore = $student->totalXp();
        $streakBefore = (int) $student->current_streak;
        $badgesBefore = DB::table('achievements as a')
            ->join('badges as b', 'b.id', '=', 'a.badge_id')
            ->where('a.user_id', $userId)
            ->pluck('a.unlocked_at', 'b.badge_key');

        DB::table('gesture_results')
            ->whereIn('practice_session_id', $student->practiceSessions()->select('id'))
            ->where(fn ($q) => $q->whereNull('recognized_gesture')
                ->orWhereIn('recognized_gesture', ['none', ...self::MOCK_LABELS]))
            ->delete();

        $completed = $student->practiceSessions()
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->with(['lesson', 'gestureResults'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $deleted = 0;
        $rescored = 0;
        $masteredLessonIds = [];

        foreach ($completed as $session) {
            if ($session->gestureResults->isEmpty()) {
                $session->delete();
                $deleted++;

                continue;
            }

            $expected = strtolower((string) $session->lesson->gesture_label);
            $bestCorrect = $session->gestureResults
                ->filter(fn ($result) => strtolower((string) $result->recognized_gesture) === $expected)
                ->max('confidence');

            $passed = $bestCorrect !== null && $bestCorrect >= PracticeService::PASS_SCORE;
            $score = $bestCorrect === null ? 0 : (int) $bestCorrect;
            $xp = $passed && ! isset($masteredLessonIds[$session->lesson_id]) ? (int) $session->lesson->xp_reward : 0;

            if ($passed) {
                $masteredLessonIds[$session->lesson_id] = true;
            }

            foreach ($session->gestureResults as $result) {
                $result->forceFill([
                    'correct' => strtolower((string) $result->recognized_gesture) === $expected
                        && $result->confidence >= PracticeService::PASS_SCORE,
                ])->save();
            }

            if ((int) $session->score !== $score || (int) $session->xp_earned !== $xp) {
                $session->forceFill(['score' => $score, 'xp_earned' => $xp])->save();
                $rescored++;
            }
        }

        $this->rebuildStreak($student);
        $this->rebuildChallengeProgress($userId, $student);
        $badgesRemoved = $this->rebuildBadges($userId, $badgesBefore);

        $student->refresh();

        return [
            'email' => $student->user->email,
            'sessions_deleted' => $deleted,
            'sessions_rescored' => $rescored,
            'xp_before' => $xpBefore,
            'xp_after' => $student->totalXp(),
            'streak_before' => $streakBefore,
            'streak_after' => (int) $student->current_streak,
            'badges_removed' => $badgesRemoved,
        ];
    }

    /**
     * Tanggal-tanggal (Y-m-d, terurut naik) saat student lulus peragaan.
     *
     * @return Collection<int, string>
     */
    private function passedDays(Student $student): Collection
    {
        return $student->practiceSessions()
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->where('score', '>=', PracticeService::PASS_SCORE)
            ->pluck('created_at')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();
    }

    private function rebuildStreak(Student $student): void
    {
        $days = $this->passedDays($student);

        $longest = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $run = $previous !== null && CarbonImmutable::parse($previous)->addDay()->toDateString() === $day ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $day;
        }

        $lastDay = $days->last();
        $isActive = $lastDay !== null && CarbonImmutable::parse($lastDay)->greaterThanOrEqualTo(CarbonImmutable::yesterday());

        $student->forceFill([
            'current_streak' => $isActive ? $run : 0,
            'longest_streak' => $longest,
            'last_activity_date' => $lastDay,
        ])->save();
    }

    /**
     * Progres challenge = jumlah peragaan lulus pada tanggal & kategori challenge.
     */
    private function rebuildChallengeProgress(int $userId, Student $student): void
    {
        $passedByDayAndCategory = $student->practiceSessions()
            ->join('lessons as l', 'l.id', '=', 'practice_sessions.lesson_id')
            ->join('materials as m', 'm.id', '=', 'l.material_id')
            ->join('categories as c', 'c.id', '=', 'm.category_id')
            ->where('practice_sessions.status', PracticeSession::STATUS_COMPLETED)
            ->where('practice_sessions.score', '>=', PracticeService::PASS_SCORE)
            ->get(['practice_sessions.created_at', 'c.slug'])
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->created_at)->toDateString()
                .'|'.(ChallengeService::CATEGORY_MAP[$row->slug] ?? $row->slug))
            ->map(fn (Collection $rows) => $rows->sortBy('created_at')->values());

        $challengeIds = DB::table('challenge_progress')->where('user_id', $userId)->pluck('challenge_id');
        $passedDates = $passedByDayAndCategory->keys()->map(fn (string $key) => explode('|', $key)[0])->unique()->values();

        $challenges = DB::table('challenges')
            ->whereIn('id', $challengeIds)
            ->orWhereIn('challenge_date', $passedDates)
            ->get();

        foreach ($challenges as $challenge) {
            $key = CarbonImmutable::parse($challenge->challenge_date)->toDateString().'|'.$challenge->category;
            $passes = $passedByDayAndCategory->get($key, collect());
            $progress = min($passes->count(), (int) $challenge->target);
            $completed = $progress >= (int) $challenge->target;

            if ($progress === 0) {
                DB::table('challenge_progress')
                    ->where('user_id', $userId)
                    ->where('challenge_id', $challenge->id)
                    ->delete();

                continue;
            }

            DB::table('challenge_progress')->updateOrInsert(
                ['user_id' => $userId, 'challenge_id' => $challenge->id],
                [
                    'progress' => $progress,
                    'completed' => $completed ? 1 : 0,
                    'completed_at' => $completed ? $passes[(int) $challenge->target - 1]->created_at : null,
                ],
            );
        }
    }

    /**
     * Hitung ulang badge dari data bersih; badge yang tetap layak mempertahankan
     * tanggal unlock aslinya.
     *
     * @param  Collection<string, string>  $badgesBefore  badge_key => unlocked_at
     * @return string[] badge_key yang dicabut
     */
    private function rebuildBadges(int $userId, Collection $badgesBefore): array
    {
        DB::table('achievements')->where('user_id', $userId)->delete();
        GamificationService::evaluateBadges($userId);

        $badgesAfter = GamificationService::unlockedKeys($userId);

        foreach ($badgesAfter as $key) {
            if ($badgesBefore->has($key)) {
                DB::table('achievements')
                    ->where('user_id', $userId)
                    ->where('badge_id', DB::table('badges')->where('badge_key', $key)->value('id'))
                    ->update(['unlocked_at' => $badgesBefore->get($key)]);
            }
        }

        return array_values(array_diff($badgesBefore->keys()->all(), $badgesAfter));
    }
}
