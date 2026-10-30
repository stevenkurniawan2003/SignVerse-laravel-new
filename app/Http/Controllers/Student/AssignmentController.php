<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Services\AssignmentService;
use App\Services\ClassroomService;
use App\Services\StudentStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tugas siswa: daftar tugas dari guru kelasnya (belum/sudah selesai),
 * bergabung ke kelas lewat kode, dan pengumpulan manual dari sesi latihan.
 * Tugas juga selesai otomatis saat siswa lulus peragaan AI (PracticeService).
 */
class AssignmentController extends Controller
{
    public function __construct(
        private AssignmentService $assignments,
        private StudentStatsService $stats,
        private ClassroomService $classrooms,
    ) {}

    public function index(Request $request): Response
    {
        $student = $this->stats->forUser($request->user());

        // Safety net untuk siswa yang bergabung sebelum tugas aktif dibagikan.
        $this->assignments->distributeActiveTo($student);

        return Inertia::render('Assignments', [
            'pending' => $this->present($this->assignments->pendingFor($student), $student),
            'completed' => $this->present($this->assignments->completedFor($student), $student),
            'classes' => $student->classrooms()
                ->with('teacher.user:id,name')
                ->orderBy('classroom_student.created_at')
                ->get()
                ->map(fn ($classroom) => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'description' => $classroom->description,
                    'teacher' => $classroom->teacher?->user?->name,
                    'joined_at' => $classroom->pivot->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $student = $this->stats->forUser($request->user());

        $classroom = $this->classrooms->joinByCode($student, $validated['code']);

        if (! $classroom) {
            return back()->withErrors(['code' => 'Kode kelas tidak ditemukan. Periksa lagi kode dari gurumu.']);
        }

        return back()->with('status', "Berhasil bergabung ke kelas {$classroom->name}!");
    }

    public function show(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $this->stats->forUser($request->user());
        abort_unless($assignment->submissions()->where('student_id', $student->id)->exists(), 404);

        return redirect()->route('vue.lesson', $assignment->lesson_id);
    }

    /**
     * Selesaikan tugas memakai sesi latihan yang sudah final (completed).
     * Skor sesi dibandingkan dengan min_score tugas.
     */
    public function submit(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $this->stats->forUser($request->user());

        $validated = $request->validate([
            'practice_session_id' => ['required', 'integer'],
        ]);

        $session = PracticeSession::query()
            ->where('student_id', $student->id)
            ->where('id', $validated['practice_session_id'])
            ->where('status', PracticeSession::STATUS_COMPLETED)
            ->first();

        if (! $session) {
            return back()->with('error',
                'Sesi latihan tidak ditemukan / belum selesai. Selesaikan latihan dulu di halaman practice.');
        }

        $outcome = $this->assignments->submitFromPractice($student, $assignment, $session);

        if ($outcome['completed']) {
            return redirect()
                ->route('student.assignments.index')
                ->with('status', 'Tugas selesai! Skor '.$outcome['score'].' (+'.$outcome['xp_earned'].' XP).');
        }

        return redirect()
            ->route('student.assignments.index')
            ->with('error', 'Skor latihan '.$outcome['score'].' belum mencapai syarat '.$assignment->min_score.'. Latihan lagi lalu kumpulkan ulang!');
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    private function present(Collection $assignments, Student $student): Collection
    {
        return $assignments->map(function (Assignment $assignment) {
            /** @var AssignmentSubmission|null $submission */
            $submission = $assignment->submissions->first();

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'teacher' => $assignment->teacher?->user?->name,
                'classroom_id' => $assignment->classroom_id,
                'classroom' => $assignment->classroom?->name,
                'lesson_id' => $assignment->lesson_id,
                'lesson' => $assignment->lesson?->title,
                'language' => strtoupper((string) $assignment->lesson?->material?->language),
                'min_score' => (int) $assignment->min_score,
                'xp_reward' => (int) $assignment->xp_reward,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'is_overdue' => $assignment->due_at !== null && $assignment->due_at->isPast(),
                'status' => $submission?->status,
                'score' => $submission?->score,
                'completed_at' => $submission?->completed_at?->toIso8601String(),
            ];
        })->values();
    }
}
