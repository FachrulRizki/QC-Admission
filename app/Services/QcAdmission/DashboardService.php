<?php

namespace App\Services\QcAdmission;

use App\Models\QualityControl;
use App\Models\BatalRanap;
use App\Models\EdukasiLanjutan;
use App\Models\UpSelling;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Aggregate all dashboard statistics.
     */
    public function getSummary(): array
    {
        $today = Carbon::today();

        // Stats
        $totalQC            = QualityControl::whereDate('created_at', $today)->count();
        $totalBatalRanap    = BatalRanap::whereDate('created_at', $today)->count();
        $totalEdukasi       = EdukasiLanjutan::whereDate('created_at', $today)->count();
        $totalUpSelling     = UpSelling::whereDate('created_at', $today)->count();

        // Avg durasi tunggu today (stored as HH:MM:SS string — convert to seconds for avg)
        $avgDurasi = QualityControl::whereDate('created_at', $today)
            ->whereNotNull('durasi_tunggu')
            ->get()
            ->avg(function ($r) {
                [$h, $m, $s] = array_pad(explode(':', $r->durasi_tunggu), 3, 0);
                return ($h * 3600) + ($m * 60) + $s;
            });

        $avgMenit = $avgDurasi ? round($avgDurasi / 60, 2) : 0;

        // Petugas breakdown (today)
        $petugasStats = QualityControl::whereDate('created_at', $today)
            ->selectRaw('petugas,
                count(*) as total,
                sum(case when status = "Edukasi" then 1 else 0 end) as edukasi,
                sum(case when status = "Edukasi lanjutan" then 1 else 0 end) as edukasi_lanjutan')
            ->groupBy('petugas')
            ->get();

        // Recent QC (last 20)
        $recentQC = QualityControl::latest()->take(20)->get();

        return [
            'stats' => [
                'jumlahEksternalPasien' => $totalQC + $totalBatalRanap,
                'durasiTungguEdukasi'   => number_format($avgMenit, 2),
                'totalQC'               => $totalQC,
                'totalBatalRanap'       => $totalBatalRanap,
                'totalEdukasiLanjutan'  => $totalEdukasi,
                'totalUpSelling'        => $totalUpSelling,
            ],
            'petugas_stats' => $petugasStats,
            'recent_qc'     => $recentQC,
        ];
    }
}
