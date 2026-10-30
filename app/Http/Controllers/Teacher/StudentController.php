<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\StudentStatsService;
use App\Services\TeacherStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen & monitoring siswa milik guru (siswa di kelas-kelasnya):
 * daftar siswa realtime, detail progres belajar, buat akun, ubah data,
 * dan reset kata sandi. Akun tidak dihapus agar riwayat belajar aman.
 */
class StudentController extends Controller
{
    public function __construct(
        private TeacherStatsService $stats,
        private StudentStatsService $studentStats,
        private ClassroomService $classrooms,
    ) {}

    public function index(Request $request): Response
    {
        $teacher = $this->stats->forUser($request->user());

        return Inertia::render('Teacher/Students', [
            'students' => $this->stats->studentsTable($teacher),
            'classes' => $teacher->classrooms()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Tabel siswa untuk polling realtime.
     */
    public function live(Request $request): JsonResponse
    {
        return response()->json([
            'students' => $this->stats->studentsTable($this->stats->forUser($request->user())),
        ]);
    }

    public function show(Request $request, Student $student): Response
    {
        $teacher = $this->authorizeStudent($request, $student);
        $student->load('user:id,name,email');

        return Inertia::render('Teacher/StudentDetail', [
            'student' => [
                'id' => $student->id,
                'name' => $student->user->name,
                'email' => $student->user->email,
                'longest_streak' => (int) $student->longest_streak,
                'classes' => $student->classrooms()
                    ->where('teacher_id', $teacher->id)
                    ->get(['classrooms.id', 'classrooms.name']),
            ],
            'summary' => $this->studentStats->dashboardSummary($student),
            'letters' => $this->stats->letterMastery($student),
            'recentSessions' => $student->practiceSessions()
                ->where('status', 'completed')
                ->with('lesson:id,title')
                ->latest('completed_at')
                ->limit(10)
                ->get()
                ->map(fn ($session) => [
                    'id' => $session->id,
                    'lesson' => $session->lesson?->title,
                    'language' => strtoupper($session->language),
                    'score' => (int) $session->score,
                    'xp' => (int) $session->xp_earned,
                    'at' => $session->completed_at?->toIso8601String(),
                ]),
            'errorAnalysis' => $this->stats->errorAnalysisForStudent($student)->take(6)->values(),
            'assignments' => $student->submissions()
                ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $teacher->id))
                ->with('assignment.lesson.material')
                ->latest()
                ->get()
                ->map(fn ($submission) => [
                    'id' => $submission->id,
                    'title' => $submission->assignment->title,
                    'lesson' => $submission->assignment->lesson?->title,
                    'language' => strtoupper((string) $submission->assignment->lesson?->material?->language),
                    'status' => $submission->status,
                    'score' => $submission->score,
                    'due_at' => $submission->assignment->due_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Guru membuatkan akun siswa baru langsung di salah satu kelasnya.
     */
    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->stats->forUser($request->user());

        $validated = $request->validate([
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('teacher_id', $teacher->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $classroom = Classroom::findOrFail($validated['classroom_id']);
        $student = $this->classrooms->createStudentAccount($classroom, $validated);

        return back()->with('status', "Akun {$student->user->name} dibuat dan masuk ke kelas {$classroom->name}. Berikan email & kata sandinya ke siswa.");
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($student->user_id)],
        ]);

        $student->user->update(['name' => $validated['name'], 'email' => strtolower($validated['email'])]);

        return back()->with('status', 'Data siswa diperbarui.');
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $student->user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('status', "Kata sandi {$student->user->name} berhasil diganti.");
    }

    private function authorizeStudent(Request $request, Student $student): Teacher
    {
        $teacher = $this->stats->forUser($request->user());
        abort_unless($teacher->hasStudent($student), 403);

        return $teacher;
    }
}
