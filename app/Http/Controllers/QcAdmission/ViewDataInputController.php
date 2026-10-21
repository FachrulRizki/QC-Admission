<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\Alasan;
use App\Models\BatalRanap;
use App\Models\QualityControl;
use App\Models\UpSelling;
use App\Services\QcAdmission\EdukasiLanjutanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ViewDataInputController extends Controller
{
    private const CACHE_TTL = 30;
    private const LIMIT     = 1000;  

    // Kolom yang dipakai Summary (agregasi per pasien) & stat card
    private const LEAN = ['id', 'no_mr', 'no_reg', 'nama_pasien', 'jaminan', 'tanggal', 'created_at'];

    public function __construct(private readonly EdukasiLanjutanService $edukasiService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
        ]);

        $from  = $request->query('date_from');
        $to    = $request->query('date_to');
        $perms = session('auth_user.permissions', []);

        $canQc     = in_array('quality-control:view', $perms, true);
        $canAlasan = in_array('alasan:view', $perms, true);

        $cacheKey = 'vdi:' . ($from ?? '-') . ':' . ($to ?? '-') . ':' . (int) $canQc . (int) $canAlasan;

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($from, $to, $canQc, $canAlasan) {
            $result = [
                'qc'          => [],
                'batal'       => $this->lean(BatalRanap::query(), $from, $to),
                'edukasi'     => ['active' => [], 'transferred' => []],
                'up'          => [],
                'alasan'      => [],
            ];

            if ($canQc) {
                // Sama seperti /api/quality-control: QC yang sudah pindah ke Edukasi Lanjutan tidak dihitung
                $result['qc'] = $this->lean(
                    QualityControl::query()->whereNotExists(fn($sub) => $sub->selectRaw('1')
                        ->from('qcw_edukasi_lanjutans')
                        ->whereColumn('qcw_edukasi_lanjutans.quality_control_id', 'qcw_quality_controls.id')),
                    $from, $to
                );
                $result['up'] = $this->lean(UpSelling::query(), $from, $to, ['tgl_daftar']);

                $split = $this->edukasiService->allSplit(['date_from' => $from, 'date_to' => $to, 'per_page' => self::LIMIT]);
                $result['edukasi'] = [
                    'active'      => $split['active']->toArray(),
                    'transferred' => $split['transferred']->toArray(),
                ];
            }

            if ($canAlasan) {
                // Alasan: baris lengkap (dipakai detail dialog), tabelnya kecil
                $result['alasan'] = $this->dateFilter(Alasan::query(), $from, $to)
                    ->latest()->limit(self::LIMIT)->get()->toArray();
            }

            return $result;  
        });

        return response()->json($data);
    }

    private function lean($query, ?string $from, ?string $to, array $extra = []): array
    {
        return $this->dateFilter($query, $from, $to)
            ->latest()
            ->limit(self::LIMIT)
            ->toBase()
            ->get(array_merge(self::LEAN, $extra))
            ->map(fn($r) => (array) $r)
            ->all();
    }

    private function dateFilter($query, ?string $from, ?string $to)
    {
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);
        return $query;
    }
}
