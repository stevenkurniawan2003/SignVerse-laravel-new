<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\TeacherStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen kelas guru: buat/ubah/hapus kelas, kode bergabung, dan
 * keanggotaan siswa (tambah siswa terdaftar atau keluarkan dari kelas).
 */
class ClassroomController extends Controller
{
    public function __construct(
        private ClassroomService $classrooms,
        private TeacherStatsService $stats,
    ) {}

    public function index(Request $request): Response
    {
        $teacher = $this->stats->forUser($request->user());

        return Inertia::render('Teacher/Classes', [
            'classes' => $teacher->classrooms()
                ->withCount(['students', 'assignments'])
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'code', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $classroom = $this->classrooms->create($this->stats->forUser($request->user()), $validated);

        return redirect()
            ->route('teacher.classes.show', $classroom)
            ->with('status', "Kelas {$classroom->name} dibuat. Bagikan kode {$classroom->code} ke siswa.");
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        $teacher = $this->authorizeClassroom($request, $classroom);

        return Inertia::render('Teacher/ClassDetail', [
            'classroom' => $classroom->only(['id', 'name', 'description', 'code']),
            'students' => $this->stats->studentsTable($teacher, $classroom),
            'assignments' => $classroom->assignments()
                ->with('lesson.material')
                ->withCount([
                    'submissions',
                    'submissions as completed_count' => fn ($query) => $query->where('status', 'completed'),
                ])
                ->latest()
                ->get()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'lesson' => $assignment->lesson?->title,
                    'language' => strtoupper((string) $assignment->lesson?->material?->language),
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'submissions_count' => $assignment->submissions_count,
                    'completed_count' => $assignment->completed_count,
                ]),
        ]);
    }

    /**
     * Tabel siswa kelas untuk polling realtime.
     */
    public function live(Request $request, Classroom $classroom): JsonResponse
    {
        $teacher = $this->authorizeClassroom($request, $classroom);

        return response()->json(['students' => $this->stats->studentsTable($teacher, $classroom)]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);

        $classroom->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('status', 'Data kelas diperbarui.');
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $name = $classroom->name;

        DB::transaction(function () use ($classroom) {
            // Tugas yang sudah dikerjakan siswa dilepas dari kelas (diarsipkan)
            // agar XP tugas mereka tidak ikut terhapus bersama kelas.
            $classroom->assignments()
                ->whereHas('submissions', fn ($query) => $query->where('status', AssignmentSubmission::STATUS_COMPLETED))
                ->update(['classroom_id' => null]);

            $classroom->delete();
        });

        return redirect()
            ->route('teacher.classes.index')
            ->with('status', "Kelas {$name} dihapus. Akun & riwayat belajar siswa tetap tersimpan.");
    }

    public function regenerateCode(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $this->classrooms->regenerateCode($classroom);

        return back()->with('status', "Kode kelas baru: {$classroom->code}. Kode lama tidak berlaku lagi.");
    }

    /**
     * Tambahkan siswa yang sudah punya akun (dicari lewat email).
     */
    public function addStudent(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);

        $validated = $request->validate(['email' => ['required', 'email']]);

        $user = User::query()
            ->where('email', strtolower($validated['email']))
            ->where('role', User::ROLE_STUDENT)
            ->first();

        if (! $user) {
            return back()->withErrors(['email' => 'Akun siswa dengan email ini tidak ditemukan. Buat akun baru lewat tombol "Buat Akun Siswa".']);
        }

        $student = $user->student ?? $user->student()->create();
        $this->classrooms->addStudent($classroom, $student);

        return back()->with('status', "{$user->name} ditambahkan ke kelas {$classroom->name}.");
    }

    public function removeStudent(Request $request, Classroom $classroom, Student $student): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $this->classrooms->removeStudent($classroom, $student);

        return back()->with('status', "{$student->user->name} dikeluarkan dari kelas. Riwayat belajarnya tetap tersimpan.");
    }

    private function authorizeClassroom(Request $request, Classroom $classroom): Teacher
    {
        $teacher = $this->stats->forUser($request->user());
        abort_unless($classroom->teacher_id === $teacher->id, 403);

        return $teacher;
    }
}
