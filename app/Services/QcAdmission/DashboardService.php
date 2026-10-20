<?php

namespace App\Services\QcAdmission;

use App\Models\QualityControl;
use App\Models\BatalRanap;
use App\Models\UpSelling;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    // Cache singkat: banyak user membuka dashboard bersamaan setelah login
    private const CACHE_TTL = 30;
    public function getSummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::today()->startOfDay();
        $to   = $dateTo   ? Carbon::parse($dateTo)->endOfDay()     : Carbon::today()->endOfDay();

        $cacheKey = 'dashboard_summary:' . $from->toDateString() . ':' . $to->toDateString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->buildSummary($from, $to));
    }

    private function buildSummary(Carbon $from, Carbon $to): array
    {
        $rows = QualityControl::query()
            ->whereBetween('created_at', [$from, $to])
            ->toBase()
            ->select(['status', 'petugas', 'durasi_tunggu', 'note'])
            ->cursor();

        $totalQC = 0;
        $perStatusCount = ['Edukasi' => 0, 'Edukasi lanjutan' => 0];
        $durasiTotal = 0;
        $durasiCount = 0;
        $petugasAgg  = []; 
        $noteCount   = [];

        foreach ($rows as $r) {
            $totalQC++;

            if (isset($perStatusCount[$r->status])) {
                $perStatusCount[$r->status]++;
            }

            $durasi = ! empty($r->durasi_tunggu) ? $this->durasiToSeconds($r->durasi_tunggu) : null;
            if ($durasi !== null) {
                $durasiTotal += $durasi;
                $durasiCount++;
            }

            if (! empty($r->petugas)) {
                $p = &$petugasAgg[$r->petugas];
                $p ??= ['total' => 0, 'Edukasi' => 0, 'Edukasi lanjutan' => 0, 'durasi_total' => 0, 'durasi_count' => 0];
                $p['total']++;
                if (isset($p[$r->status]) && $r->status !== 'total') {
                    $p[$r->status]++;
                }
                if ($durasi !== null) {
                    $p['durasi_total'] += $durasi;
                    $p['durasi_count']++;
                }
                unset($p);
            }

            if (! empty($r->note)) {
                $noteCount[$r->note] = ($noteCount[$r->note] ?? 0) + 1;
            }
        }

        $totalEdukasi     = $perStatusCount['Edukasi'];
        $totalEdukasiLanj = $perStatusCount['Edukasi lanjutan'];
        $totalBatalRanap  = BatalRanap::whereBetween('created_at', [$from, $to])->count();
        $totalUpSelling   = UpSelling::whereBetween('created_at',  [$from, $to])->count();

        $avgMenit = $durasiCount ? round(($durasiTotal / $durasiCount) / 60, 2) : 0;

        // ── Avg durasi per petugas (tabel kiri atas) ──────────────────────────
        $avgPerPetugas = collect($petugasAgg)
            ->filter(fn($p) => $p['durasi_count'] > 0)
            ->map(fn($p, $petugas) => [
                'petugas'          => $petugas,
                'avg_durasi_menit' => (int) round(($p['durasi_total'] / $p['durasi_count']) / 60),
                'jumlah_pasien'    => $p['durasi_count'],
            ])
            ->sortByDesc('avg_durasi_menit')
            ->values();

        // ── Matrix detail edukasi per petugas × status (tabel tengah atas) ───
        $matrix = collect($petugasAgg)
            ->sortKeys()
            ->map(fn($p, $petugas) => [
                'petugas'          => $petugas,
                'Edukasi'          => $p['Edukasi'],
                'Edukasi lanjutan' => $p['Edukasi lanjutan'],
                'total'            => $p['total'],
            ])
            ->sortByDesc('total')
            ->values();

        // ── Jumlah edukasi per petugas (tabel kiri bawah) ────────────────────
        $edukasiPerPetugas = collect($petugasAgg)
            ->map(fn($p, $petugas) => [
                'petugas'        => $petugas,
                'jumlah_edukasi' => $p['total'],
            ])
            ->sortByDesc('jumlah_edukasi')
            ->values();

        // ── Funnel: jumlah edukasi per status ─────────────────────────────────
        $perStatus = collect([
            ['status' => 'Edukasi',          'count' => $totalEdukasi],
            ['status' => 'Edukasi lanjutan',  'count' => $totalEdukasiLanj],
        ])->filter(fn($r) => $r['count'] > 0)->values();

        // ── Bar chart: pasien per note/kamar ──────────────────────────────────
        arsort($noteCount);
        $perKamar = collect(array_slice($noteCount, 0, 8, true))
            ->map(fn($count, $note) => ['kamar' => (string) $note, 'count' => $count])
            ->values();

        // ── Recent QC (tabel bawah full width) — tanpa kolom ttd ─────────────
        $recentQC = QualityControl::query()
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->take(100)
            ->toBase()
            ->get([
                'tanggal', 'no_mr', 'no_reg', 'nama_pasien', 'status', 'jam_input',
                'petugas', 'note', 'edukasi_kamar', 'durasi_tunggu',
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

    private function durasiToSeconds(string $durasi): int
    {
        $p = array_pad(explode(':', $durasi), 3, 0);
        return ((int) $p[0] * 3600) + ((int) $p[1] * 60) + (int) $p[2];
    }
}
