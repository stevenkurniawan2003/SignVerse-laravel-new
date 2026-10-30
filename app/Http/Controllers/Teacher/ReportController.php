<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Services\TeacherReportService;
use App\Services\TeacherStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan belajar siswa untuk guru: filter kelas & periode, tampilan
 * halaman (bisa dicetak / disimpan PDF) dan ekspor CSV untuk Excel.
 */
class ReportController extends Controller
{
    public function __construct(
        private TeacherReportService $reports,
        private TeacherStatsService $stats,
    ) {}

    public function index(Request $request): Response
    {
        $teacher = $this->stats->forUser($request->user());
        [$classroom, $from, $to, $range] = $this->filters($request, $teacher);

        return Inertia::render('Teacher/Reports', [
            'report' => $this->reports->build($teacher, $classroom, $from, $to),
            'filters' => [
                'classroom_id' => $classroom?->id,
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'classes' => $teacher->classrooms()->orderBy('name')->get(['id', 'name']),
            'teacherName' => $request->user()->name,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Ekspor laporan per siswa ke CSV (pemisah titik koma agar langsung rapi
     * di Excel berbahasa Indonesia).
     */
    public function export(Request $request): StreamedResponse
    {
        $teacher = $this->stats->forUser($request->user());
        [$classroom, $from, $to] = $this->filters($request, $teacher);
        $report = $this->reports->build($teacher, $classroom, $from, $to);

        $filename = sprintf(
            'laporan-signtech-%s-%s_%s.csv',
            $classroom ? str($classroom->name)->slug() : 'semua-kelas',
            $from->toDateString(),
            $to->toDateString(),
        );

        return response()->streamDownload(function () use ($report, $classroom, $from, $to, $request) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8

            $write = fn (array $row) => fputcsv($out, $row, ';');
            $summary = $report['summary'];

            $write(['Laporan Belajar SignTech']);
            $write(['Guru', $request->user()->name]);
            $write(['Kelas', $classroom?->name ?? 'Semua kelas']);
            $write(['Periode', $from->format('d/m/Y').' - '.$to->format('d/m/Y')]);
            $write([]);
            $write(['Ringkasan']);
            $write(['Jumlah siswa', $summary['students']]);
            $write(['Siswa aktif berlatih', $summary['active_students']]);
            $write(['Total latihan', $summary['sessions']]);
            $write(['Latihan berhasil', $summary['passed']]);
            $write(['Tingkat keberhasilan (%)', $summary['pass_rate']]);
            $write(['Akurasi rata-rata (%)', $summary['avg_score']]);
            $write(['Penyelesaian tugas (%)', $summary['assignment_completion']]);
            $write([]);
            $write(['Per Siswa']);
            $write(['Nama', 'Email', 'Kelas', 'Status', 'Latihan', 'Berhasil', 'Keberhasilan (%)', 'Akurasi rata-rata (%)', 'Huruf baru dikuasai', 'Total huruf dikuasai', 'Tugas selesai', 'Total tugas', 'Terakhir aktif']);
            foreach ($report['students'] as $student) {
                $write([
                    $student['name'],
                    $student['email'],
                    $student['classes'],
                    $student['status'],
                    $student['sessions'],
                    $student['passed'],
                    $student['pass_rate'],
                    $student['avg_score'],
                    $student['new_letters'],
                    $student['mastered_total'],
                    $student['assignments_done'],
                    $student['assignments_total'],
                    $student['last_active'] ? CarbonImmutable::parse($student['last_active'])->format('d/m/Y H:i') : '-',
                ]);
            }

            $write([]);
            $write(['Tugas']);
            $write(['Judul', 'Kelas', 'Isyarat', 'Selesai', 'Total siswa', 'Rata-rata skor (%)', 'Batas waktu']);
            foreach ($report['assignments'] as $assignment) {
                $write([
                    $assignment['title'],
                    $assignment['classroom'],
                    $assignment['lesson'].' ('.$assignment['language'].')',
                    $assignment['completed'],
                    $assignment['total'],
                    $assignment['avg_score'] ?? '-',
                    $assignment['due_at'] ? CarbonImmutable::parse($assignment['due_at'])->format('d/m/Y') : 'Tanpa batas',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: ?Classroom, 1: CarbonImmutable, 2: CarbonImmutable, 3: string}
     */
    private function filters(Request $request, Teacher $teacher): array
    {
        $validated = $request->validate([
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('teacher_id', $teacher->id)],
            'range' => ['nullable', Rule::in(['7d', '30d', 'month', 'custom'])],
            'from' => ['nullable', 'date', 'required_if:range,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:range,custom'],
        ]);

        $range = $validated['range'] ?? '7d';
        $today = CarbonImmutable::today();

        [$from, $to] = match ($range) {
            '30d' => [$today->subDays(29), $today],
            'month' => [$today->startOfMonth(), $today],
            'custom' => [CarbonImmutable::parse($validated['from']), CarbonImmutable::parse($validated['to'])],
            default => [$today->subDays(6), $today],
        };

        // Batasi rentang agar laporan tetap ringan.
        if ($from->diffInDays($to) > 366) {
            $from = $to->subDays(366);
        }

        $classroom = isset($validated['classroom_id']) ? Classroom::find($validated['classroom_id']) : null;

        return [$classroom, $from, $to, $range];
    }
}
