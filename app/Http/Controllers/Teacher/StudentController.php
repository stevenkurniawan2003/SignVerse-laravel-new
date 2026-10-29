<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\TeacherStatsService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Monitoring siswa (Phase 7): daftar seluruh siswa dengan ringkasan
 * aktivitas + halaman detail per siswa.
 */
class StudentController extends Controller
{
    public function __construct(
        private TeacherStatsService $stats,
    ) {}

    /**
     * Daftar siswa untuk monitoring.
     */
    public function index(): InertiaResponse
    {
        $students = $this->stats->studentsOverview();
        $payload = [
            'students' => $students,
            'title' => 'Monitoring Siswa',
        ];
        view()->share($payload);

        return Inertia::render('Classroom', $payload);
    }

    /**
     * Detail satu siswa: statistik, progress kategori, sesi terakhir,
     * achievement, dan gesture yang sering salah oleh siswa tersebut.
     */
    public function show(Student $student): InertiaResponse
    {
        $detail = $this->stats->studentDetail($student);
        $errorAnalysis = $this->stats->errorAnalysisForStudent($student);

        $detail['assignments'] = collect($detail['assignments'])->map(function ($sub) {
            $status = is_object($sub) ? ($sub->status ?? '') : ($sub['status'] ?? '');
            if (is_object($sub)) {
                $sub->status_label = $status === 'completed' ? 'Selesai' : 'Pending';
            } else {
                $sub['status_label'] = $status === 'completed' ? 'Selesai' : 'Pending';
            }
            return $sub;
        });

        $detail['challenges_today'] = collect($detail['challenges_today'])->map(function ($ch) {
            $rewardXp = is_object($ch) ? ($ch->reward_xp ?? 0) : ($ch['reward_xp'] ?? 0);
            if (is_object($ch)) {
                $ch->reward_xp_text = '+'.$rewardXp.' XP';
            } else {
                $ch['reward_xp_text'] = '+'.$rewardXp.' XP';
            }
            return $ch;
        });

        $payload = [
            'detail' => $detail,
            'errorAnalysis' => $errorAnalysis,
            'student' => $student->load('user'),
            'status' => 'Selesai',
        ];
        view()->share($payload);

        return Inertia::render('Classroom', $payload);
    }
}
