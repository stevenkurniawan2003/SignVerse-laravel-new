<?php

use App\Services\PracticeDataCleanupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('signtech:clean-practice-data {--dry-run : Tampilkan perubahan tanpa menyimpan} {--include-demo : Ikut proses akun demo @signteach.id}', function (PracticeDataCleanupService $cleanup) {
    DB::beginTransaction();

    try {
        $report = $cleanup->clean((bool) $this->option('include-demo'));
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }

    $this->table(
        ['Akun', 'Sesi dihapus', 'Skor/XP dikoreksi', 'XP sebelum', 'XP sesudah', 'Streak', 'Badge dicabut'],
        collect($report)->map(fn (array $row) => [
            $row['email'],
            $row['sessions_deleted'],
            $row['sessions_rescored'],
            $row['xp_before'],
            $row['xp_after'],
            "{$row['streak_before']} → {$row['streak_after']}",
            implode(', ', $row['badges_removed']) ?: '-',
        ])->all(),
    );

    if ($this->option('dry-run')) {
        DB::rollBack();
        $this->warn('Dry run: tidak ada perubahan yang disimpan.');

        return;
    }

    DB::commit();
    $this->info('Data latihan berhasil dibersihkan.');
})->purpose('Bersihkan data latihan lama agar sesuai aturan penilaian AI terbaru');
