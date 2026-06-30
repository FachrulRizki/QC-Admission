<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PasienController extends Controller
{
    /**
     * Search pasien rawat inap.
     * Query params:
     *   search  — cari No_Reg / No_MR / Nama_Pasien (min 2 karakter)
     *   no_reg  — lookup tepat satu pasien (untuk autofill form)
     *
     * Jika RSUS_DB_ENABLED=false → kembalikan mock.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $noReg  = trim($request->query('no_reg', ''));

        if (! config('services.rsus_db_enabled', false)) {
            return response()->json([
                'data'   => $this->mockPasien($search ?: $noReg),
                'source' => 'mock',
            ]);
        }

        try {
            $data = $this->queryRsus($search, $noReg);

            return response()->json(['data' => $data, 'total' => count($data), 'source' => 'rsus_db']);
        } catch (\Exception $e) {
            Log::warning('DB RSUS gagal, fallback mock.', ['error' => $e->getMessage()]);

            return response()->json([
                'data'   => $this->mockPasien($search ?: $noReg),
                'source' => 'mock_fallback',
            ]);
        }
    }

    private function queryRsus(string $search, string $noReg): array
    {
        $innerSql = "
            SELECT
                YEAR(P.Tanggal) AS Tahun_Masuk, MONTH(P.Tanggal) AS Bulan_Masuk,
                DATENAME(month,P.Tanggal) AS Nama_Bulan, DATENAME(DW,P.Tanggal) AS Nama_Hari,
                P.No_Reg, P.No_MR, R.Nama_Pasien, CB.KET_BAYAR,
                P.Tanggal AS Tgl_Daftar, P.Jam AS Jam_Daftar,
                E.Tanggal AS Tgl_SPRI, E.Jam AS Jam_SPRI, DO.Nama_Dokter,
                F.Tanggal AS Tgl_Gen_Concent, F.Jam AS Jam_Gen_Concent,
                (SELECT TOP(1) Tgl_Mulai FROM dbo.TR_KAMAR WHERE No_Reg=P.No_Reg) AS Tgl_Dapat_Kamar,
                (SELECT TOP(1) Jam_Mulai FROM dbo.TR_KAMAR AS TK1 WHERE No_Reg=P.No_Reg) AS Jam_Dapat_Kamar,
                C.Nama_Ruang, K.Nama_Kelas, D.Nama_Bangsal, G.KET_MASUK,
                (SELECT TOP(1) TglPindah FROM dbo.ASESMEN_TRANSFER_PASIEN WHERE No_Reg=P.No_Reg AND NamaPetugasMenerima<>'') AS tgl_masuk_Kamar,
                (SELECT TOP(1) JamPindah FROM dbo.ASESMEN_TRANSFER_PASIEN AS ATP1 WHERE No_Reg=P.No_Reg AND NamaPetugasMenerima<>'') AS Jam_masuk_Kamar,
                P.CaraMasuk_IGD, P.Medis, P.Status, E.Status AS Status_SPRI,
                (SELECT COUNT(*) FROM dbo.TR_BIAYARINCI   WHERE No_Reg=P.No_Reg) AS Jm_Tindakan,
                (SELECT COUNT(*) FROM dbo.TR_MASTER_RESEP WHERE No_Reg=P.No_Reg) AS Jm_Resep,
                (SELECT COUNT(*) FROM dbo.TR_MASTER_LAB   WHERE No_Reg=P.No_Reg) AS Jm_LAB,
                (SELECT COUNT(*) FROM dbo.ASESMEN_BAYI_BARU_LAHIR WHERE No_Reg=P.No_Reg) AS Ass_Bayi,
                SPRI.created_at AS Time_ApproveSPRI
            FROM dbo.PENDAFTARAN AS P
            LEFT OUTER JOIN dbo.TR_KAMAR AS B ON P.No_Reg=B.No_Reg
            LEFT OUTER JOIN dbo.M_RUANG  AS C ON B.Kode_Ruang=C.Kode_Ruang
            LEFT OUTER JOIN dbo.M_BANGSAL AS D ON C.Kode_Bangsal=D.Kode_Bangsal
            LEFT OUTER JOIN dbo.ASESMEN_SURAT_PERMINTAAN_RI AS E ON P.No_Reg=E.No_Reg
            LEFT OUTER JOIN dbo.ASESMEN_GENERAL_CONSENT AS F ON P.No_Reg=F.No_Reg
            INNER JOIN dbo.M_CARAMASUK AS G ON P.Kode_Masuk=G.KODE_MASUK
            INNER JOIN dbo.M_CARABAYAR AS CB ON P.Kode_Bayar=CB.KODE_BAYAR
            LEFT OUTER JOIN dbo.REGISTER_PASIEN AS R ON P.No_MR=R.No_MR
            LEFT OUTER JOIN dbo.approve_spri AS SPRI ON SPRI.No_Reg COLLATE DATABASE_DEFAULT=P.No_Reg COLLATE DATABASE_DEFAULT
            LEFT OUTER JOIN dbo.DOKTER AS DO ON E.NamaUser=DO.Kode_Dokter
            LEFT OUTER JOIN dbo.M_KELAS AS K ON C.Kode_Kelas=K.Kode_Kelas
            WHERE (P.Kode_Masuk IN ('1','2','3'))
              AND (CAST(P.Tanggal AS DATE) >= DATEADD(DAY,-3,GETDATE()))
              AND (P.No_MR <> '000000')
        ";

        [$cond, $params] = $this->buildCondition($search, $noReg);
        $top     = $noReg !== '' ? 'TOP 1' : 'TOP 100';
        $orderBy = 'ORDER BY L.Tgl_Daftar DESC';
        $sql     = "SELECT {$top} * FROM ({$innerSql}) AS L {$cond} {$orderBy}";

        $rows = DB::connection('rsus')->select($sql, $params);

        return collect($rows)->map(fn($r) => $this->mapRow((array) $r))->toArray();
    }

    private function buildCondition(string $search, string $noReg): array
    {
        if ($noReg !== '') {
            return ['WHERE L.No_Reg = ?', [$noReg]];
        }
        if ($search !== '') {
            $like = "%{$search}%";
            return ['WHERE L.No_Reg LIKE ? OR L.No_MR LIKE ? OR L.Nama_Pasien LIKE ?', [$like, $like, $like]];
        }
        return ['', []];
    }

    private function mapRow(array $r): array
    {
        return [
            'no_reg'       => $r['No_Reg']      ?? '',
            'no_mr'        => $r['No_MR']        ?? '',
            'nama_pasien'  => $r['Nama_Pasien']  ?? '',
            'ket_bayar'    => $r['KET_BAYAR']    ?? '',
            'nama_ruang'   => $r['Nama_Ruang']   ?? '',
            'nama_bangsal' => $r['Nama_Bangsal'] ?? '',
            'nama_kelas'   => $r['Nama_Kelas']   ?? '',
            'tgl_daftar'   => $r['Time_Daftar']  ?? '',
            'nama_dokter'  => $r['Nama_Dokter']  ?? '',
            'keterangan'   => $r['Keterangan']   ?? '',
            'label'        => ($r['No_Reg'] ?? '') . ' — ' . ($r['Nama_Pasien'] ?? ''),
        ];
    }

    private function mockPasien(string $search = ''): array
    {
        $data = [
            ['no_reg'=>'569142','no_mr'=>'569142','nama_pasien'=>'ONGKI SAPUTRA, TN','ket_bayar'=>'BPJS','nama_ruang'=>'PS ATAS 01','nama_bangsal'=>'PAHLAWAN ATAS','nama_kelas'=>'KELAS VIP','tgl_daftar'=>'2026-06-28 19:56:40'],
            ['no_reg'=>'REG001','no_mr'=>'813500','nama_pasien'=>'ELLY MAYA, NY',    'ket_bayar'=>'BPJS','nama_ruang'=>'DAHLIA 2',  'nama_bangsal'=>'DAHLIA',  'nama_kelas'=>'Kelas 1','tgl_daftar'=>'2026-06-29 08:00:00'],
            ['no_reg'=>'REG002','no_mr'=>'575360','nama_pasien'=>'IDH SUBINGSEN, NY','ket_bayar'=>'BPJS','nama_ruang'=>'MAWAR 3',   'nama_bangsal'=>'MAWAR',   'nama_kelas'=>'Kelas 2','tgl_daftar'=>'2026-06-29 10:30:00'],
            ['no_reg'=>'REG003','no_mr'=>'087220','nama_pasien'=>'RUSMINI, NY',      'ket_bayar'=>'Umum','nama_ruang'=>'ANGGREK 1', 'nama_bangsal'=>'ANGGREK', 'nama_kelas'=>'Kelas 3','tgl_daftar'=>'2026-06-28 14:15:00'],
            ['no_reg'=>'REG004','no_mr'=>'816302','nama_pasien'=>'PUSPA SARI, AN',   'ket_bayar'=>'BPJS','nama_ruang'=>'ICU 2',     'nama_bangsal'=>'ICU',     'nama_kelas'=>'VIP',    'tgl_daftar'=>'2026-06-27 09:00:00'],
            ['no_reg'=>'REG005','no_mr'=>'712405','nama_pasien'=>'BUDI SANTOSO, TN', 'ket_bayar'=>'Asuransi','nama_ruang'=>'MAWAR 1','nama_bangsal'=>'MAWAR',  'nama_kelas'=>'Kelas 1','tgl_daftar'=>'2026-06-27 11:00:00'],
            ['no_reg'=>'REG006','no_mr'=>'654321','nama_pasien'=>'SRI WAHYUNI, NY',  'ket_bayar'=>'BPJS','nama_ruang'=>'DAHLIA 3',  'nama_bangsal'=>'DAHLIA',  'nama_kelas'=>'Kelas 2','tgl_daftar'=>'2026-06-29 07:30:00'],
            ['no_reg'=>'REG007','no_mr'=>'789012','nama_pasien'=>'AHMAD FAUZI, TN',  'ket_bayar'=>'Umum','nama_ruang'=>'ANGGREK 2', 'nama_bangsal'=>'ANGGREK', 'nama_kelas'=>'Kelas 3','tgl_daftar'=>'2026-06-28 16:00:00'],
            ['no_reg'=>'REG008','no_mr'=>'345678','nama_pasien'=>'DEWI RAHAYU, NY',  'ket_bayar'=>'BPJS','nama_ruang'=>'MAWAR 2',   'nama_bangsal'=>'MAWAR',   'nama_kelas'=>'Kelas 1','tgl_daftar'=>'2026-06-29 09:15:00'],
            ['no_reg'=>'REG009','no_mr'=>'901234','nama_pasien'=>'HENDRA WIJAYA, TN','ket_bayar'=>'Asuransi','nama_ruang'=>'ICU 1', 'nama_bangsal'=>'ICU',     'nama_kelas'=>'VIP',    'tgl_daftar'=>'2026-06-28 13:45:00'],
            ['no_reg'=>'REG010','no_mr'=>'567890','nama_pasien'=>'SITI AMINAH, NY',  'ket_bayar'=>'BPJS','nama_ruang'=>'DAHLIA 1',  'nama_bangsal'=>'DAHLIA',  'nama_kelas'=>'Kelas 2','tgl_daftar'=>'2026-06-27 15:00:00'],
        ];

        $data = array_map(fn($d) => array_merge($d, ['label' => $d['no_reg'] . ' — ' . $d['nama_pasien']]), $data);

        if ($search === '') return $data;

        $s = strtolower($search);
        return array_values(array_filter($data, fn($d) =>
            str_contains(strtolower($d['no_reg']), $s) ||
            str_contains(strtolower($d['no_mr']), $s) ||
            str_contains(strtolower($d['nama_pasien']), $s)
        ));
    }
}
