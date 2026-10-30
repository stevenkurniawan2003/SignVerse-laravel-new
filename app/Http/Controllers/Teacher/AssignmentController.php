<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Services\AssignmentService;
use App\Services\PracticeService;
use App\Services\TeacherStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tugas guru: dibuat untuk satu kelas dan satu pelajaran (huruf). Tugas
 * muncul di menu Tugas siswa anggota kelas dan selesai otomatis saat siswa
 * lulus peragaan AI dengan skor >= min_score.
 */
class AssignmentController extends Controller
{
    public function __construct(
        private AssignmentService $assignments,
        private TeacherStatsService $stats,
    ) {}

    public function index(Request $request): Response
    {
        $teacher = $this->stats->forUser($request->user());

        return Inertia::render('Teacher/Assignments', [
            'assignments' => Assignment::query()
                ->where('teacher_id', $teacher->id)
                ->with(['lesson.material', 'classroom:id,name'])
                ->withCount([
                    'submissions',
                    'submissions as completed_count' => fn ($query) => $query->where('status', 'completed'),
                ])
                ->latest()
                ->get()
                ->map(fn (Assignment $assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'classroom' => $assignment->classroom?->name,
                    'lesson' => $assignment->lesson?->title,
                    'language' => strtoupper((string) $assignment->lesson?->material?->language),
                    'min_score' => (int) $assignment->min_score,
                    'xp_reward' => (int) $assignment->xp_reward,
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'submissions_count' => $assignment->submissions_count,
                    'completed_count' => $assignment->completed_count,
                ]),
            'classes' => $teacher->classrooms()->withCount('students')->orderBy('name')->get(['id', 'name']),
            'lessons' => Lesson::query()
                ->with('material:id,title,language,category_id', 'material.category:id,slug')
                ->whereHas('material.category', fn ($query) => $query->whereIn('slug', PracticeService::AI_SUPPORTED_CATEGORIES))
                ->get()
                ->sortBy(fn (Lesson $lesson) => $lesson->material->language.$lesson->title)
                ->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'language' => strtoupper($lesson->material->language),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->stats->forUser($request->user());

        $validated = $request->validate([
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('teacher_id', $teacher->id)],
            'lesson_id' => ['required', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'min_score' => ['required', 'integer', 'min:'.PracticeService::PASS_SCORE, 'max:100'],
            'xp_reward' => ['required', 'integer', 'min:1', 'max:500'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ], [
            'min_score.min' => 'Skor minimal tidak boleh di bawah '.PracticeService::PASS_SCORE.' (batas lulus AI).',
            'due_at.after' => 'Batas waktu harus setelah sekarang.',
        ]);

        $assignment = $this->assignments->createAndDistribute([
            'teacher_id' => $teacher->id,
            ...$validated,
        ]);

        return redirect()
            ->route('teacher.assignments.show', $assignment)
            ->with('status', "Tugas dibagikan ke {$assignment->submissions()->count()} siswa di kelas {$assignment->classroom->name}.");
    }

    public function show(Request $request, Assignment $assignment): Response
    {
        abort_unless($assignment->teacher_id === $request->user()->teacher?->id, 403);

        $assignment->load(['lesson.material', 'classroom:id,name', 'submissions.student.user:id,name']);

        return Inertia::render('Teacher/AssignmentDetail', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'classroom' => $assignment->classroom?->name,
                'classroom_id' => $assignment->classroom_id,
                'lesson' => $assignment->lesson?->title,
                'language' => strtoupper((string) $assignment->lesson?->material?->language),
                'min_score' => (int) $assignment->min_score,
                'xp_reward' => (int) $assignment->xp_reward,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'created_at' => $assignment->created_at?->toIso8601String(),
            ],
            'stats' => $this->assignments->assignmentStats($assignment),
            'submissions' => $assignment->submissions
                ->sortBy(fn ($submission) => [$submission->status !== 'completed', $submission->student?->user?->name])
                ->map(fn ($submission) => [
                    'student_id' => $submission->student_id,
                    'name' => $submission->student?->user?->name ?? 'Siswa',
                    'status' => $submission->status,
                    'score' => $submission->score,
                    'xp_earned' => (int) $submission->xp_earned,
                    'completed_at' => $submission->completed_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->teacher_id === $request->user()->teacher?->id, 403);

        // XP tugas tersimpan di submission; menghapusnya akan mengurangi XP siswa.
        if ($assignment->submissions()->where('status', 'completed')->exists()) {
            return back()->with('error', 'Tugas ini sudah diselesaikan siswa, jadi tidak bisa dihapus agar XP mereka tidak hilang.');
        }

        DB::transaction(fn () => $assignment->delete());

        return redirect()
            ->route('teacher.assignments.index')
            ->with('status', 'Tugas dihapus.');
    }
}
