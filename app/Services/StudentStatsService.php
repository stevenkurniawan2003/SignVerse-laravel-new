<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Statistik dashboard & progress student (phase 3).
 *
 * XP & level dihitung agregat:
 * level = tabel level bernama dari GamificationService::levelFromXp(),
 * streak = streak aktif dari tanggal latihan (GamificationService::streakInfo).
 */
class StudentStatsService
{
    /**
     * Ringkasan lengkap untuk dashboard student.
     *
     * @return array{xp: int, level: array{name: string, color: string, xp: int, threshold: int, next_xp: int|null, pct: float}, streak: int, longest_streak: int, sessions: int, avg_score: float, lessons_mastered: int, lessons_total: int}
     */
    public function dashboardSummary(Student $student): array
    {
        $xp = $student->totalXp();
        $level = GamificationService::levelFromXp($xp);

        $sessions = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED);

        $avgScore = (float) (clone $sessions)->avg('score');

        $streak = GamificationService::streakInfo($student->user_id)['streak'];

        return [
            'xp' => $xp,
            'level' => $level,
            'streak' => $streak,
            'longest_streak' => max((int) $student->longest_streak, $streak),
            'sessions' => (clone $sessions)->count(),
            'avg_score' => round($avgScore, 1),
            'lessons_mastered' => $this->lessonsMastered($student),
            'lessons_total' => $this->aiVerifiableLessons()->count(),
        ];
    }

    /**
     * Sesi latihan terakhir untuk aktivitas di dashboard.
     */
    public function recentSessions(Student $student, int $limit = 5): Collection
    {
        return PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->with(['lesson.material.category'])
            ->latest('completed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Progress per kategori: persentase lesson yang pernah dikerjakan
     * dengan skor lulus (>= 60).
     *
     * @return Collection<int, array{category: Category, total: int, done: int, percent: int}>
     */
    public function progressByCategory(Student $student): Collection
    {
        $lessonIdsByCategory = Category::query()
            ->with(['materials.lessons:id,material_id'])
            ->get()
            ->mapWithKeys(fn (Category $category) => [
                $category->id => $category->materials
                    ->flatMap(fn ($material) => $material->lessons->pluck('id'))
                    ->unique()
                    ->values(),
            ]);

        $passedLessonIds = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->where('score', '>=', 60)
            ->pluck('lesson_id')
            ->unique();

        return Category::query()
            ->orderBy('order')
            ->get()
            ->map(function (Category $category) use ($lessonIdsByCategory, $passedLessonIds) {
                $lessonIds = $lessonIdsByCategory->get($category->id, collect());
                $total = $lessonIds->count();
                $done = $lessonIds->intersect($passedLessonIds)->count();

                return [
                    'category' => $category,
                    'total' => $total,
                    'done' => $done,
                    'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
                ];
            });
    }

    /**
     * Jumlah lesson yang pernah dilatih dengan skor lulus.
     */
    public function lessonsMastered(Student $student): int
    {
        return PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->where('score', '>=', 60)
            ->distinct('lesson_id')
            ->count('lesson_id');
    }

    /**
     * Rekomendasi lesson untuk dilatih berikutnya:
     * prioritas yang pernah dilatih tapi belum lulus, lalu yang belum dilatih.
     */
    public function recommendedLessons(Student $student, int $limit = 3): Collection
    {
        $scores = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->selectRaw('lesson_id, MAX(score) as best_score')
            ->groupBy('lesson_id')
            ->pluck('best_score', 'lesson_id');

        $attemptedFailing = $this->aiVerifiableLessons()
            ->whereIn('id', $scores->filter(fn ($score) => $score < 60)->keys())
            ->with('material.category')
            ->orderBy('difficulty')
            ->limit($limit)
            ->get();

        if ($attemptedFailing->count() >= $limit) {
            return $attemptedFailing;
        }

        $fresh = $this->aiVerifiableLessons()
            ->whereNotIn('id', $scores->keys())
            ->with('material.category')
            ->orderBy('difficulty')
            ->orderBy('order')
            ->limit($limit - $attemptedFailing->count())
            ->get();

        return $attemptedFailing->concat($fresh);
    }

    /**
     * Lesson yang bisa diselesaikan siswa, yaitu yang peragaannya bisa dinilai AI.
     *
     * @return Builder<Lesson>
     */
    private function aiVerifiableLessons(): Builder
    {
        return Lesson::query()->whereHas(
            'material.category',
            fn (Builder $query) => $query->whereIn('slug', PracticeService::AI_SUPPORTED_CATEGORIES),
        );
    }

    /**
     * Student model dari user yang sedang login.
     * Profil dibuat otomatis jika belum ada (safety net untuk user lama).
     */
    public function forUser(User $user): Student
    {
        return $user->student()->firstOrCreate(['user_id' => $user->id]);
    }
}
