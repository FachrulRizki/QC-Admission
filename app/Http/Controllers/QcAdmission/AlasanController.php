<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\AlasanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlasanController extends Controller
{
    public function __construct(
        private readonly AlasanService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->paginate($request->only(['search', 'date_from', 'date_to', 'per_page', 'page']))
        );
    }

    /**
     * GET /api/alasan/pendaftaran-aktif
     */
    public function pendaftaranAktif(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));

        if (! config('services.rsus_db_enabled', false)) {
            return response()->json([
                'data'   => $this->mockPendaftaranAktif($search),
                'source' => 'mock',
            ]);
        }

        try {
            $data = $this->queryPendaftaranAktif($search);
            return response()->json(['data' => $data, 'total' => count($data), 'source' => 'rsus_db']);
        } catch (\Exception $e) {
            Log::warning('DB RSUS pendaftaran-aktif gagal, fallback mock.', ['error' => $e->getMessage()]);
            return response()->json([
                'data'   => $this->mockPendaftaranAktif($search),
                'source' => 'mock_fallback',
            ]);
        }
    }

    private function queryPendaftaranAktif(string $search): array
    {
        $sql = "
            SELECT TOP 200
                P.No_Reg,
                P.No_MR,
                R.Nama_Pasien,
                CB.KET_BAYAR,
                CONVERT(varchar, P.Tanggal, 23) AS Tgl_Daftar,
                CONVERT(varchar, P.Jam, 108)    AS Jam_Daftar,
                C.Nama_Ruang,
                D.Nama_Bangsal,
                K.Nama_Kelas,
                G.KET_MASUK
            FROM dbo.PENDAFTARAN AS P
            LEFT JOIN dbo.REGISTER_PASIEN AS R  ON P.No_MR    = R.No_MR
            LEFT JOIN dbo.M_CARABAYAR     AS CB ON P.Kode_Bayar = CB.KODE_BAYAR
            LEFT JOIN dbo.TR_KAMAR        AS B  ON P.No_Reg   = B.No_Reg
            LEFT JOIN dbo.M_RUANG         AS C  ON B.Kode_Ruang = C.Kode_Ruang
            LEFT JOIN dbo.M_BANGSAL       AS D  ON C.Kode_Bangsal = D.Kode_Bangsal
            LEFT JOIN dbo.M_KELAS         AS K  ON C.Kode_Kelas = K.Kode_Kelas
            LEFT JOIN dbo.M_CARAMASUK     AS G  ON P.Kode_Masuk = G.KODE_MASUK
            WHERE P.Status = N'1'
              AND P.Status_Pulang = N'belum'
        ";

        $params = [];
        if ($search !== '') {
            $like    = "%{$search}%";
            $sql    .= " AND (P.No_Reg LIKE ? OR P.No_MR LIKE ? OR R.Nama_Pasien LIKE ?)";
            $params  = [$like, $like, $like];
        }

        $sql .= " ORDER BY P.Tanggal DESC, P.Jam DESC";

        $rows = DB::connection('rsus')->select($sql, $params);

        return collect($rows)->map(fn($r) => [
            'no_reg'       => $r->No_Reg        ?? '',
            'no_mr'        => $r->No_MR         ?? '',
            'nama_pasien'  => trim($r->Nama_Pasien ?? ''),
            'ket_bayar'    => trim($r->KET_BAYAR  ?? ''),
            'tgl_daftar'   => $r->Tgl_Daftar    ?? '',
            'jam_daftar'   => $r->Jam_Daftar     ?? '',
            'nama_ruang'   => $r->Nama_Ruang     ?? '',
            'nama_bangsal' => $r->Nama_Bangsal   ?? '',
            'nama_kelas'   => $r->Nama_Kelas     ?? '',
            'ket_masuk'    => $r->KET_MASUK      ?? '',
        ])->toArray();
    }

    private function mockPendaftaranAktif(string $search = ''): array
    {
        $data = [
            ['no_reg' => 'REG-2026-001', 'no_mr' => '100001', 'nama_pasien' => 'BUDI SANTOSO, TN',    'ket_bayar' => 'BPJS',          'tgl_daftar' => '2026-09-17', 'jam_daftar' => '08:00:00', 'nama_ruang' => 'IGD', 'nama_bangsal' => 'IGD',    'nama_kelas' => '', 'ket_masuk' => 'IGD'],
            ['no_reg' => 'REG-2026-002', 'no_mr' => '100002', 'nama_pasien' => 'SITI RAHAYU, NY',     'ket_bayar' => 'Umum',          'tgl_daftar' => '2026-09-17', 'jam_daftar' => '09:15:00', 'nama_ruang' => 'IGD', 'nama_bangsal' => 'IGD',    'nama_kelas' => '', 'ket_masuk' => 'Poli'],
            ['no_reg' => 'REG-2026-003', 'no_mr' => '100003', 'nama_pasien' => 'AGUS WIBOWO, TN',     'ket_bayar' => 'BPJS',          'tgl_daftar' => '2026-09-17', 'jam_daftar' => '10:30:00', 'nama_ruang' => 'IGD', 'nama_bangsal' => 'IGD',    'nama_kelas' => '', 'ket_masuk' => 'IGD'],
            ['no_reg' => 'REG-2026-004', 'no_mr' => '100004', 'nama_pasien' => 'DEWI KUSUMA, NY',     'ket_bayar' => 'Asuransi',      'tgl_daftar' => '2026-09-17', 'jam_daftar' => '11:00:00', 'nama_ruang' => 'IGD', 'nama_bangsal' => 'IGD',    'nama_kelas' => '', 'ket_masuk' => 'Rujukan'],
            ['no_reg' => 'REG-2026-005', 'no_mr' => '100005', 'nama_pasien' => 'HENDRA GUNAWAN, TN', 'ket_bayar' => 'BPJS',          'tgl_daftar' => '2026-09-16', 'jam_daftar' => '19:45:00', 'nama_ruang' => 'IGD', 'nama_bangsal' => 'IGD',    'nama_kelas' => '', 'ket_masuk' => 'IGD'],
            ['no_reg' => 'REG-2026-006', 'no_mr' => '100006', 'nama_pasien' => 'RATNA SARI, NY',      'ket_bayar' => 'Jasa Raharja',  'tgl_daftar' => '2026-09-16', 'jam_daftar' => '22:10:00', 'nama_ruang' => 'IGD', 'nama_bangsal' => 'IGD',    'nama_kelas' => '', 'ket_masuk' => 'IGD'],
            ['no_reg' => 'REG-2026-007', 'no_mr' => '100007', 'nama_pasien' => 'JOKO PURNOMO, TN',   'ket_bayar' => 'Umum',          'tgl_daftar' => '2026-09-15', 'jam_daftar' => '07:00:00', 'nama_ruang' => 'IGD', 'nama_bangsal' => 'IGD',    'nama_kelas' => '', 'ket_masuk' => 'Langsung'],
        ];

        if ($search === '') return $data;

        $s = strtolower($search);
        return array_values(array_filter($data, fn($d) =>
            str_contains(strtolower($d['no_reg'] ?? ''), $s) ||
            str_contains(strtolower($d['no_mr'] ?? ''), $s) ||
            str_contains(strtolower($d['nama_pasien'] ?? ''), $s)
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'      => 'required|string',
            'jam_input'    => 'required|string',
            'no_reg'       => 'required|string|max:20',
            'no_mr'        => 'nullable|string|max:20',
            'nama_pasien'  => 'nullable|string|max:100',
            'jaminan'      => 'nullable|string|max:50',
            'tgl_daftar'   => 'nullable|string',
            'jam_daftar'   => 'nullable|string',
            'nama_ruang'   => 'nullable|string|max:100',
            'nama_bangsal' => 'nullable|string|max:100',
            'alasan'       => 'required|string|max:100',
            'catatan'      => 'nullable|string|max:2000',
            'petugas'      => 'nullable|string|max:100',
        ]);

        $record = $this->service->create($validated);

        ActivityLog::record('alasan', 'create',
            "Alasan '{$record->alasan}' — {$record->nama_pasien} ({$record->no_reg})", null, null, $record->petugas);

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'alasan'  => 'sometimes|string|max:100',
            'catatan' => 'nullable|string|max:2000',
            'petugas' => 'sometimes|string|max:100',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record('alasan', 'update',
            "Alasan diupdate — {$record->nama_pasien} ({$record->no_reg})", null, null, $record->petugas);

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('alasan', 'delete',
            "Alasan dihapus — {$record->nama_pasien} ({$record->no_reg})");
        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
