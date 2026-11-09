<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data kalender dashboard per tanggal (zona waktu aplikasi):
 *   - practice : latihan yang selesai pada tanggal itu (titik hijau)
 *   - deadlines: tugas guru yang tenggatnya jatuh pada tanggal itu (titik oranye)
 *
 * Siswa melihat latihannya sendiri dan tugas yang dibagikan kepadanya.
 * Guru melihat latihan siswa di kelasnya dan tugas yang ia buat.
 */
class CalendarService
{
    /** Rentang maksimal satu permintaan (6 minggu, cukup untuk satu bulan penuh). */
    public const MAX_RANGE_DAYS = 42;

    /** Jumlah judul pelajaran yang ditampilkan per tanggal. */
    private const LESSON_TITLES_PER_DAY = 5;

    /**
     * @return array<string, array{practice: array{sessions: int, passed: int, xp: int, lessons: list<string>}|null, deadlines: list<array<string, mixed>>}>
     */
    public function forStudent(Student $student, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $practice = $this->practiceByDay(
            PracticeSession::query()->where('student_id', $student->id),
            $start,
            $end,
        );

        $deadlines = Assignment::query()
            ->with(['lesson:id,title', 'submissions' => fn ($query) => $query->where('student_id', $student->id)])
            ->whereHas('submissions', fn (Builder $query) => $query->where('student_id', $student->id))
            ->where(fn (Builder $query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->whereBetween('due_at', [$start->startOfDay(), $end->endOfDay()])
            ->orderBy('due_at')
            ->get()
            ->map(function (Assignment $assignment): array {
                $isCompleted = $assignment->submissions->first()?->status === AssignmentSubmission::STATUS_COMPLETED;

                return [
                    'date' => $assignment->due_at->toDateString(),
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'lesson_title' => $assignment->lesson?->title,
                    'due_time' => $assignment->due_at->format('H:i'),
                    'completed' => $isCompleted,
                    'overdue' => ! $isCompleted && $assignment->due_at->isPast(),
                    'status_label' => $isCompleted ? 'Selesai' : ($assignment->due_at->isPast() ? 'Terlewat' : 'Belum dikerjakan'),
                    'link' => "/student/assignments/{$assignment->id}",
                ];
            });

        return $this->mergeDays($practice, $deadlines);
    }

    /**
     * @return array<string, array{practice: array{sessions: int, passed: int, xp: int, lessons: list<string>}|null, deadlines: list<array<string, mixed>>}>
     */
    public function forTeacher(Teacher $teacher, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $practice = $this->practiceByDay(
            PracticeSession::query()->whereIn('student_id', $teacher->studentsQuery()->select('students.id')),
            $start,
            $end,
        );

        $deadlines = $teacher->assignments()
            ->with('lesson:id,title')
            ->withCount([
                'submissions',
                'submissions as completed_count' => fn ($query) => $query->where('status', AssignmentSubmission::STATUS_COMPLETED),
            ])
            ->whereBetween('due_at', [$start->startOfDay(), $end->endOfDay()])
            ->orderBy('due_at')
            ->get()
            ->map(fn (Assignment $assignment): array => [
                'date' => $assignment->due_at->toDateString(),
                'id' => $assignment->id,
                'title' => $assignment->title,
                'lesson_title' => $assignment->lesson?->title,
                'due_time' => $assignment->due_at->format('H:i'),
                'completed' => $assignment->submissions_count > 0 && $assignment->completed_count === $assignment->submissions_count,
                'overdue' => $assignment->due_at->isPast(),
                'status_label' => "{$assignment->completed_count}/{$assignment->submissions_count} siswa selesai",
                'link' => "/teacher/assignments/{$assignment->id}",
            ]);

        return $this->mergeDays($practice, $deadlines);
    }

    /**
     * Ringkasan latihan selesai per tanggal.
     *
     * @param  Builder<PracticeSession>  $sessions
     * @return Collection<string, array{sessions: int, passed: int, xp: int, lessons: list<string>}>
     */
    private function practiceByDay(Builder $sessions, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $sessions
            ->with('lesson:id,title')
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
            ->get(['id', 'lesson_id', 'score', 'xp_earned', 'completed_at'])
            ->groupBy(fn (PracticeSession $session) => $session->completed_at->toDateString())
            ->map(fn (Collection $daySessions): array => [
                'sessions' => $daySessions->count(),
                'passed' => $daySessions->where('score', '>=', PracticeService::PASS_SCORE)->count(),
                'xp' => (int) $daySessions->sum('xp_earned'),
                'lessons' => $daySessions
                    ->where('score', '>=', PracticeService::PASS_SCORE)
                    ->map(fn (PracticeSession $session) => $session->lesson?->title)
                    ->filter()
                    ->unique()
                    ->take(self::LESSON_TITLES_PER_DAY)
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $practice
     * @param  Collection<int, array<string, mixed>>  $deadlines
     * @return array<string, array{practice: array<string, mixed>|null, deadlines: list<array<string, mixed>>}>
     */
    private function mergeDays(Collection $practice, Collection $deadlines): array
    {
        $deadlinesByDay = $deadlines->groupBy('date');
        $dates = $practice->keys()->merge($deadlinesByDay->keys())->unique()->sort()->values();

        return $dates->mapWithKeys(fn (string $date) => [
            $date => [
                'practice' => $practice->get($date),
                'deadlines' => $deadlinesByDay->get($date, collect())
                    ->map(fn (array $deadline) => collect($deadline)->except('date')->all())
                    ->values()
                    ->all(),
            ],
        ])->all();
    }
}
