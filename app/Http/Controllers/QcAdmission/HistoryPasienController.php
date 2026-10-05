<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\QualityControl;
use App\Models\EdukasiLanjutan;
use App\Models\BatalRanap;
use App\Models\UpSelling;
use App\Services\QcAdmission\EdukasiLanjutanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryPasienController extends Controller
{
    public function __construct(
        private readonly EdukasiLanjutanService $edukasiService
    ) {}

    /**
     * GET /api/history-pasien/{no_mr}
     */
    public function byNoMr(string $noMr): JsonResponse
    {
        $noMr = trim($noMr);

        // 1. Quality Control (Edukasi Awal) — TANPA whereNotExists filter
        $qcList = QualityControl::query()
            ->where(fn($q) => $q->where('no_mr', $noMr)->orWhere('no_reg', $noMr))
            ->orderBy('tanggal')
            ->get([
                'id','no_mr','no_reg','nama_pasien','jaminan',
                'tanggal','jam_input','tgl_daftar','jam_daftar',
                'petugas','status','status_ranap',
                'edukasi_kamar','note','keluarga_pasien',
                'ttd_keluarga_pasien','durasi_tunggu',
                'ketersediaan_kamar','diagnosa',
                'created_at','updated_at',
            ])
            ->map(fn($r) => array_merge($r->toArray(), [
                '_module' => 'edukasi-awal',
                '_label'  => 'Edukasi Awal',
            ]));

        // 2. Edukasi Lanjutan — semua record termasuk yang sudah transferred
        $edukasiList = EdukasiLanjutan::query()
            ->where(fn($q) => $q->where('no_mr', $noMr)->orWhere('no_reg', $noMr))
            ->orderBy('tanggal')
            ->get([
                'id','no_mr','no_reg','nama_pasien','jaminan',
                'tanggal','bulan','petugas','keluarga_pasien',
                'status','status_ranap','ranap_at',
                'edukasi_kamar','note','quality_control_id',
                'created_at','updated_at',
            ]);

        // Enrich dengan info RSUS (sudah dapat bed / belum)
        $this->edukasiService->enrichPublic($edukasiList);

        $edukasiMapped = $edukasiList->map(function ($r) {
            $arr = $r->toArray();
            $arr['keterangan'] = $r->keterangan ?? null;
            if ($r->has_transfer ?? false) {
                $arr['_module'] = 'sudah-dapat-bed';
                $arr['_label']  = 'Sudah Dapat Bed';
            } else {
                $arr['_module'] = 'edukasi-lanjutan';
                $arr['_label']  = 'Edukasi Lanjutan';
            }
            return $arr;
        });

        // 3. Batal Ranap
        $batalList = BatalRanap::query()
            ->where(fn($q) => $q->where('no_mr', $noMr)->orWhere('no_reg', $noMr))
            ->orderBy('tanggal')
            ->get()
            ->map(fn($r) => array_merge($r->toArray(), [
                '_module' => 'batal-ranap',
                '_label'  => 'Batal Ranap',
            ]));

        // 4. Up Selling
        $upList = UpSelling::query()
            ->where(fn($q) => $q->where('no_mr', $noMr)->orWhere('no_reg', $noMr))
            ->orderBy('tgl_daftar')
            ->get()
            ->map(fn($r) => array_merge($r->toArray(), [
                '_module' => 'up-selling',
                '_label'  => 'Up Selling',
            ]));

        // Gabungkan semua, urutkan terlama → terbaru (frontend nanti reverse untuk display terbaru di atas)
        $allEvents = collect()
            ->concat($qcList)
            ->concat($edukasiMapped)
            ->concat($batalList)
            ->concat($upList)
            ->sortBy(fn($ev) => $ev['tanggal'] ?? $ev['tgl_daftar'] ?? '')
            ->values();

        // Info pasien dari record pertama yang ditemukan
        $first  = $allEvents->first() ?? [];
        $pasien = [
            'no_mr'       => $first['no_mr']      ?? $noMr,
            'no_reg'      => $first['no_reg']      ?? null,
            'nama_pasien' => $first['nama_pasien'] ?? '—',
            'jaminan'     => $first['jaminan']     ?? '—',
        ];

        return response()->json([
            'pasien' => $pasien,
            'events' => $allEvents->values(),
            'counts' => [
                'edukasi_awal'      => $qcList->count(),
                'edukasi_lanjutan'  => $edukasiMapped->where('_module', 'edukasi-lanjutan')->count(),
                'sudah_dapat_bed'   => $edukasiMapped->where('_module', 'sudah-dapat-bed')->count(),
                'batal_ranap'       => $batalList->count(),
                'up_selling'        => $upList->count(),
                'total'             => $allEvents->count(),
            ],
        ]);
    }
}
