<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\QualityControl;
use App\Models\EdukasiLanjutan;
use App\Models\BatalRanap;
use App\Models\BooklistAdmission;
use App\Models\UpSelling;
use App\Services\QcAdmission\EdukasiLanjutanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryPasienController extends Controller
{
    public function __construct(
        private readonly EdukasiLanjutanService $edukasiService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $search  = mb_strtolower(trim((string) $request->query('search', '')));
        $perPage = max(1, min((int) $request->query('per_page', 10), 5000));
        $page    = max(1, (int) $request->query('page', 1));

        $patients = $this->patientIndex();

        if ($search !== '') {
            $patients = array_values(array_filter($patients, fn($p) =>
                str_contains(mb_strtolower((string) $p['nama_pasien']), $search) ||
                str_contains(mb_strtolower((string) $p['no_mr']), $search) ||
                str_contains(mb_strtolower((string) $p['no_reg']), $search)
            ));
        }

        $total = count($patients);
        $rows  = array_slice($patients, ($page - 1) * $perPage, $perPage);

        // Pisahkan Edukasi Lanjutan vs Sudah Dapat Bed (cek RSUS) — hanya untuk halaman ini
        $eduRegs = array_merge(...array_map(fn($p) => $p['_edu_regs'], $rows ?: [['_edu_regs' => []]]));
        try {
            $ket = $eduRegs ? $this->edukasiService->keteranganMap($eduRegs) : [];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('History pasien: gagal cek RSUS.', ['error' => $e->getMessage()]);
            $ket = [];
        }

        $data = array_map(function ($p) use ($ket) {
            foreach ($p['_edu_regs'] as $noReg) {
                ($ket[$noReg] ?? null) === 'Sudah Masuk Kamar'
                    ? $p['counts']['sudah-dapat-bed']++
                    : $p['counts']['edukasi-lanjutan']++;
            }
            unset($p['_edu_regs'], $p['_sort']);
            return $p;
        }, $rows);

        return response()->json([
            'data'         => $data,
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'per_page'     => $perPage,
        ]);
    }

    /**
     * Agregat semua pasien dari 4 modul (kolom kecil saja, streaming).
     * Di-cache 60 detik sebagai array biasa (cache.serializable_classes=false).
     */
    private function patientIndex(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('history_pasien_index', 60, function () {
            $map = [];

            $sources = [
                ['edukasi-awal', QualityControl::query(),  'tanggal'],
                ['edukasi',      EdukasiLanjutan::query(), 'tanggal'],
                ['batal-ranap',  BatalRanap::query(),      'tanggal'],
                ['up-selling',   UpSelling::query(),       'tgl_daftar'],
                ['booklist',     BooklistAdmission::query(), 'tanggal_booklist'],
            ];

            foreach ($sources as [$module, $query, $tglCol]) {
                $rows = $query->toBase()
                    ->select(['id', 'no_mr', 'no_reg', 'nama_pasien', 'jaminan', $tglCol . ' as tgl', 'created_at'])
                    ->cursor();

                foreach ($rows as $r) {
                    $key = $r->no_mr ?: ($r->no_reg ?: 'id-' . $module . '-' . $r->id);
                    $sort = (string) $r->created_at;

                    $p = &$map[$key];
                    $p ??= [
                        'no_mr'       => $r->no_mr ?: $r->no_reg,
                        'no_reg'      => $r->no_reg,
                        'nama_pasien' => $r->nama_pasien,
                        'jaminan'     => $r->jaminan,
                        'tanggal'     => $r->tgl,
                        'total'       => 0,
                        'counts'      => ['edukasi-awal' => 0, 'edukasi-lanjutan' => 0, 'sudah-dapat-bed' => 0, 'batal-ranap' => 0, 'up-selling' => 0, 'booklist' => 0],
                        '_edu_regs'   => [],
                        '_sort'       => $sort,
                    ];

                    $p['total']++;
                    if ($module === 'edukasi') {
                        $p['_edu_regs'][] = (string) $r->no_reg;
                    } else {
                        $p['counts'][$module]++;
                    }
                    if ($sort > $p['_sort']) {
                        $p['_sort']   = $sort;
                        $p['tanggal'] = $r->tgl;
                    }
                    unset($p);
                }
            }

            // Aktivitas terbaru di atas
            uasort($map, fn($a, $b) => strcmp($b['_sort'], $a['_sort']));

            return array_values($map);
        });
    }

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
            // Semua kolom (data per pasien, kecil). Daftar kolom eksplisit sebelumnya memuat
            // 'ketersediaan_kamar' & 'diagnosa' yang tidak ada di tabel QC → query selalu error.
            ->get()
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

        // 5. Booklist Admission — termasuk yang sudah dapat kamar (sudah keluar dari list booklist)
        $booklistList = BooklistAdmission::query()
            ->where(fn($q) => $q->where('no_mr', $noMr)->orWhere('no_reg', $noMr))
            ->orderBy('created_at')
            ->get()
            ->map(fn($r) => array_merge($r->toArray(), [
                'tanggal' => $r->tanggal_booklist,
                '_module' => 'booklist',
                '_label'  => $r->sudah_dapat_kamar ? 'Booklist — Sudah Dapat Kamar' : 'Booklist Admission',
            ]));

        // Gabungkan semua, urutkan terlama → terbaru (frontend nanti reverse untuk display terbaru di atas)
        $allEvents = collect()
            ->concat($qcList)
            ->concat($edukasiMapped)
            ->concat($batalList)
            ->concat($upList)
            ->concat($booklistList)
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
                'booklist'          => $booklistList->count(),
                'total'             => $allEvents->count(),
            ],
        ]);
    }
}
