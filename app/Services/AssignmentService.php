<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\PracticeSession;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assignment (phase 6):
 *   - teacher membuat tugas pada sebuah lesson dengan deadline & syarat skor.
 *   - pembagian ke student dibuat eksplisit (baris assignment_submissions
 *     berstatus "assigned") agar guru bisa memantau siapa yang belum mengerjakan.
 *   - student menyelesaikan lewat sesi latihan gesture: skor sesi >= min_score
 *     menandakan tugas selesai dan XP reward diberikan (sekali saja).
 */
class AssignmentService
{
    /**
     * Buat tugas baru + bagikan ke anggota kelasnya (atau subset tertentu).
     * Tugas tanpa kelas (data lama) dibagikan ke semua student.
     *
     * @param  Collection<int, Student>|null  $students  Null/empty = anggota kelas tugas.
     */
    public function createAndDistribute(array $data, ?Collection $students = null): Assignment
    {
        return DB::transaction(function () use ($data, $students) {
            $assignment = Assignment::create($data);

            $targets = match (true) {
                $students !== null && $students->isNotEmpty() => $students,
                $assignment->classroom_id !== null => $assignment->classroom->students,
                default => Student::all(),
            };

            foreach ($targets as $student) {
                AssignmentSubmission::firstOrCreate([
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                ]);
            }

            return $assignment;
        });
    }

    /**
     * Bagikan semua tugas yang masih aktif (belum lewat deadline / tanpa
     * deadline) ke satu student — untuk student yang mendaftar setelah tugas
     * dibuat guru. Idempoten: tugas yang sudah dibagikan tidak diduplikasi.
     *
     * @return int Jumlah tugas baru yang dibagikan.
     */
    public function distributeActiveTo(Student $student): int
    {
        $activeAssignments = Assignment::query()
            ->whereIn('classroom_id', $student->classrooms()->select('classrooms.id'))
            ->where(fn ($query) => $query
                ->whereNull('due_at')
                ->orWhere('due_at', '>=', now()))
            ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $student->id))
            ->get();

        foreach ($activeAssignments as $assignment) {
            AssignmentSubmission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ]);
        }

        return $activeAssignments->count();
    }

    /**
     * Bagikan tugas aktif satu kelas ke siswa yang baru bergabung.
     */
    public function distributeClassroomAssignmentsTo(Classroom $classroom, Student $student): void
    {
        $classroom->assignments()
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()))
            ->get()
            ->each(fn (Assignment $assignment) => AssignmentSubmission::firstOrCreate([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ]));
    }

    /**
     * Selesaikan otomatis tugas yang menargetkan lesson ini setelah siswa
     * lulus peragaan AI (dipanggil PracticeService::recordVerifiedAttempt).
     * Hanya tugas yang sudah dibuka dan belum lewat deadline.
     *
     * @return array<int, array{title: string, xp_earned: int}> tugas yang baru selesai
     */
    public function completeFromPractice(Student $student, PracticeSession $session): array
    {
        $assignments = Assignment::query()
            ->where('lesson_id', $session->lesson_id)
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()))
            ->whereHas('submissions', fn ($query) => $query
                ->where('student_id', $student->id)
                ->where('status', '!=', AssignmentSubmission::STATUS_COMPLETED))
            ->get();

        $completed = [];
        foreach ($assignments as $assignment) {
            $outcome = $this->submitFromPractice($student, $assignment, $session);
            if ($outcome['completed']) {
                $completed[] = ['title' => $assignment->title, 'xp_earned' => $outcome['xp_earned']];
            }
        }

        return $completed;
    }

    /**
     * Hubungkan sesi latihan dengan tugas student yang masih "assigned":
     * skor sesi >= min_score → completed + XP; di bawah itu → tetap assigned
     * (bisa mencoba lagi).
     *
     * @return array{completed: bool, score: int, xp_earned: int}
     */
    public function submitFromPractice(Student $student, Assignment $assignment, PracticeSession $session): array
    {
        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $submission) {
            abort(404, 'Kamu tidak mendapat tugas ini.');
        }

        if ($submission->status === AssignmentSubmission::STATUS_COMPLETED) {
            return [
                'completed' => true,
                'score' => (int) $submission->score,
                'xp_earned' => (int) $submission->xp_earned,
            ];
        }

        $score = (int) $session->score;
        $passed = $score >= $assignment->min_score;
        $xp = $passed ? $assignment->xp_reward : 0;

        $submission->forceFill([
            'status' => $passed ? AssignmentSubmission::STATUS_COMPLETED : AssignmentSubmission::STATUS_IN_PROGRESS,
            'score' => max($score, (int) $submission->score),
            'xp_earned' => $passed ? $assignment->xp_reward : $submission->xp_earned,
            'submitted_at' => now(),
            'completed_at' => $passed ? now() : null,
        ])->save();

        return [
            'completed' => $passed,
            'score' => $score,
            'xp_earned' => $passed ? $assignment->xp_reward : 0,
        ];
    }

    /**
     * Tugas milik student yang belum selesai (untuk halaman assignment student).
     */
    public function pendingFor(Student $student): Collection
    {
        return Assignment::query()
            ->whereHas('submissions', fn ($query) => $query
                ->where('student_id', $student->id)
                ->whereIn('status', [AssignmentSubmission::STATUS_ASSIGNED, AssignmentSubmission::STATUS_IN_PROGRESS]))
            ->with(['lesson.material.category', 'teacher.user', 'classroom:id,name', 'submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Tugas milik student yang sudah selesai.
     */
    public function completedFor(Student $student): Collection
    {
        return Assignment::query()
            ->whereHas('submissions', fn ($query) => $query
                ->where('student_id', $student->id)
                ->where('status', AssignmentSubmission::STATUS_COMPLETED))
            ->with(['lesson.material.category', 'teacher.user', 'classroom:id,name', 'submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderByDesc('due_at')
            ->get();
    }

    /**
     * Statistik pengerjaan satu tugas untuk guru.
     *
     * @return array{total: int, assigned: int, in_progress: int, completed: int, avg_score: float|null}
     */
    public function assignmentStats(Assignment $assignment): array
    {
        $submissions = $assignment->submissions;

        return [
            'total' => $submissions->count(),
            'assigned' => $submissions->where('status', AssignmentSubmission::STATUS_ASSIGNED)->count(),
            'in_progress' => $submissions->where('status', AssignmentSubmission::STATUS_IN_PROGRESS)->count(),
            'completed' => $submissions->where('status', AssignmentSubmission::STATUS_COMPLETED)->count(),
            'avg_score' => $submissions->where('status', AssignmentSubmission::STATUS_COMPLETED)
                ->avg('score') !== null
                ? round($submissions->where('status', AssignmentSubmission::STATUS_COMPLETED)->avg('score'), 1)
                : null,
        ];
    }
}
