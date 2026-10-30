<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\TeacherStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard guru — ringkasan kelas milik guru, aktivitas latihan siswa
 * terbaru, dan gesture yang sering salah. Data di-poll realtime lewat live().
 */
class TeacherDashboardController extends Controller
{
    public function __construct(
        private TeacherStatsService $stats,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Teacher/Dashboard', $this->payload($request));
    }

    /**
     * Data realtime dashboard (di-poll setiap beberapa detik).
     */
    public function live(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $teacher = $this->stats->forUser($request->user());

        return [
            'overview' => $this->stats->teacherOverview($teacher),
            'classes' => $teacher->classrooms()
                ->withCount(['students', 'assignments'])
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'activity' => $this->stats->recentActivity($teacher),
            'students' => $this->stats->studentsTable($teacher)->sortByDesc('xp')->values(),
            'errorAnalysis' => $this->stats->errorAnalysisForLesson(null, $teacher)->take(6)->values(),
            'activeAssignments' => $this->stats->activeAssignments($teacher)
                ->load('classroom:id,name')
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'classroom' => $assignment->classroom?->name,
                    'lesson' => $assignment->lesson?->title,
                    'language' => strtoupper((string) $assignment->lesson?->material?->language),
                    'due_at' => $assignment->due_at?->toIso8601String(),
                ]),
            'generatedAt' => now()->toIso8601String(),
        ];
    }
}
