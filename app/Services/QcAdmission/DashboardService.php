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
     * @param string|null $dateFrom  Y-m-d  (default: today)
     * @param string|null $dateTo    Y-m-d  (default: today)
     */
    public function getSummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::today()->startOfDay();
        $to   = $dateTo   ? Carbon::parse($dateTo)->endOfDay()     : Carbon::today()->endOfDay();

        // ── Base queries dengan filter tanggal ────────────────────────────────
        $qcQuery = QualityControl::whereBetween('created_at', [$from, $to]);

        $allQC            = $qcQuery->get();
        $totalQC          = $allQC->count();
        $totalEdukasi     = $allQC->where('status', 'Edukasi')->count();
        $totalEdukasiLanj = $allQC->where('status', 'Edukasi lanjutan')->count();
        $totalBatalRanap  = BatalRanap::whereBetween('created_at', [$from, $to])->count();
        $totalUpSelling   = UpSelling::whereBetween('created_at',  [$from, $to])->count();

        // ── Avg durasi tunggu ─────────────────────────────────────────────────
        $durasiSec = $allQC->filter(fn($r) => !empty($r->durasi_tunggu))
            ->avg(function ($r) {
                $p = array_pad(explode(':', $r->durasi_tunggu), 3, 0);
                return ((int)$p[0] * 3600) + ((int)$p[1] * 60) + (int)$p[2];
            });
        $avgMenit = $durasiSec ? round($durasiSec / 60, 2) : 0;

        // ── Avg durasi per petugas (tabel kiri atas) ──────────────────────────
        $avgPerPetugas = $allQC->filter(fn($r) => !empty($r->durasi_tunggu) && !empty($r->petugas))
            ->groupBy('petugas')
            ->map(function ($g) {
                $avg = $g->avg(function ($r) {
                    $p = array_pad(explode(':', $r->durasi_tunggu), 3, 0);
                    return ((int)$p[0] * 3600) + ((int)$p[1] * 60) + (int)$p[2];
                });
                return [
                    'petugas'          => $g->first()->petugas,
                    'avg_durasi_menit' => (int) round($avg / 60),
                    'jumlah_pasien'    => $g->count(),
                ];
            })
            ->sortByDesc('avg_durasi_menit')
            ->values();

        // ── Matrix detail edukasi per petugas × status (tabel tengah atas) ───
        $petugasList = $allQC->filter(fn($r) => !empty($r->petugas))
            ->pluck('petugas')->unique()->sort()->values();

        $matrix = $petugasList->map(function ($petugas) use ($allQC) {
            $rows = $allQC->where('petugas', $petugas);
            return [
                'petugas'          => $petugas,
                'Edukasi'          => $rows->where('status', 'Edukasi')->count(),
                'Edukasi lanjutan' => $rows->where('status', 'Edukasi lanjutan')->count(),
                'total'            => $rows->count(),
            ];
        })->sortByDesc('total')->values();

        // ── Jumlah edukasi per petugas (tabel kiri bawah) ────────────────────
        $edukasiPerPetugas = $allQC->filter(fn($r) => !empty($r->petugas))
            ->groupBy('petugas')
            ->map(fn($g) => [
                'petugas'        => $g->first()->petugas,
                'jumlah_edukasi' => $g->count(),
            ])
            ->sortByDesc('jumlah_edukasi')
            ->values();

        // ── Funnel: jumlah edukasi per status ─────────────────────────────────
        $perStatus = collect([
            ['status' => 'Edukasi',          'count' => $totalEdukasi],
            ['status' => 'Edukasi lanjutan',  'count' => $totalEdukasiLanj],
        ])->filter(fn($r) => $r['count'] > 0)->values();

        // ── Bar chart: pasien per note/kamar ──────────────────────────────────
        $perKamar = $allQC->filter(fn($r) => !empty($r->note))
            ->groupBy('note')
            ->map(fn($g, $note) => ['kamar' => $note, 'count' => $g->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        // ── Recent QC (tabel bawah full width) ───────────────────────────────
        $recentQC = QualityControl::whereBetween('created_at', [$from, $to])
            ->latest()
            ->take(100)
            ->get()
            ->map(fn($r) => [
                'tanggal'       => $r->tanggal,
                'no_mr'         => $r->no_mr,
                'no_reg'        => $r->no_reg,
                'nama_pasien'   => $r->nama_pasien,
                'status'        => $r->status,
                'jam_input'     => $r->jam_input,
                'petugas'       => $r->petugas,
                'note'          => $r->note,
                'edukasi_kamar' => $r->edukasi_kamar,
                'durasi_tunggu' => $r->durasi_tunggu,
            ]);

        return [
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
            'stats' => [
                'jumlahEdukasiPasien'  => $totalQC,
                'durasiTungguEdukasi'  => number_format($avgMenit, 2),
                'totalEdukasi'         => $totalEdukasi,
                'totalEdukasiLanjutan' => $totalEdukasiLanj,
                'totalBatalRanap'      => $totalBatalRanap,
                'totalUpSelling'       => $totalUpSelling,
            ],
            'avg_per_petugas'     => $avgPerPetugas,
            'matrix'              => $matrix,
            'edukasi_per_petugas' => $edukasiPerPetugas,
            'per_status'          => $perStatus,
            'per_kamar'           => $perKamar,
            'recent_qc'           => $recentQC,
        ];
    }
}
