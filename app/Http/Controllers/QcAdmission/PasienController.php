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
        // Subquery menggunakan query lengkap dari requirement (tanpa filter tanggal di inner,
        // filter tanggal ada di WHERE outer agar search by No_Reg lama tetap bisa)
        $innerSql = "
            SELECT
                P.No_Reg, P.No_MR, R.Nama_Pasien, CB.KET_BAYAR,
                P.Tanggal AS Tgl_Daftar,
                CONVERT(varchar, P.Jam, 108) AS Jam_Daftar_Str,
                { fn CONCAT({ fn CONCAT(CONVERT(varchar, P.Tanggal, 23), ' ') }, CONVERT(varchar, P.Jam, 108)) } AS Time_Daftar,
                C.Nama_Ruang, K.Nama_Kelas, D.Nama_Bangsal, G.KET_MASUK,
                CASE
                    WHEN E.Tgl_SPRI IS NULL THEN 'Observasi'
                    ELSE CASE
                        WHEN E.Status = 'Belum' THEN 'Antri Admisi'
                        ELSE CASE
                            WHEN E.Status = 'Sudah' AND (SELECT TOP(1) Tgl_Mulai FROM dbo.TR_KAMAR WHERE No_Reg=P.No_Reg) IS NULL THEN 'Belum Dapat Kamar'
                            ELSE CASE
                                WHEN (SELECT TOP(1) TglPindah FROM dbo.ASESMEN_TRANSFER_PASIEN WHERE No_Reg=P.No_Reg AND NamaPetugasMenerima<>'') IS NOT NULL THEN 'Sudah Masuk Kamar'
                                ELSE 'Belum Diantar'
                            END
                        END
                    END
                END AS Keterangan
            FROM dbo.PENDAFTARAN AS P
            LEFT OUTER JOIN dbo.TR_KAMAR AS B ON P.No_Reg=B.No_Reg
            LEFT OUTER JOIN dbo.M_RUANG  AS C ON B.Kode_Ruang=C.Kode_Ruang
            LEFT OUTER JOIN dbo.M_BANGSAL AS D ON C.Kode_Bangsal=D.Kode_Bangsal
            LEFT OUTER JOIN dbo.ASESMEN_SURAT_PERMINTAAN_RI AS E ON P.No_Reg=E.No_Reg
            INNER JOIN dbo.M_CARAMASUK AS G ON P.Kode_Masuk=G.KODE_MASUK
            INNER JOIN dbo.M_CARABAYAR AS CB ON P.Kode_Bayar=CB.KODE_BAYAR
            LEFT OUTER JOIN dbo.REGISTER_PASIEN AS R ON P.No_MR=R.No_MR
            LEFT OUTER JOIN dbo.DOKTER AS DO ON E.NamaUser=DO.Kode_Dokter
            LEFT OUTER JOIN dbo.M_KELAS AS K ON C.Kode_Kelas=K.Kode_Kelas
            WHERE (P.Kode_Masuk IN ('1','2','3'))
              AND (P.No_MR <> '000000')
        ";

        [$cond, $params] = $this->buildCondition($search, $noReg);
        $top     = $noReg !== '' ? 'TOP 1' : 'TOP 150';
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
            return [
                'WHERE (L.No_Reg LIKE ? OR L.No_MR LIKE ? OR L.Nama_Pasien LIKE ?)',
                [$like, $like, $like],
            ];
        }
        // Tanpa search: hanya 3 hari terakhir agar tidak berat
        return [
            "WHERE CAST(L.Tgl_Daftar AS DATE) >= DATEADD(DAY, -3, GETDATE())",
            [],
        ];
    }

    private function mapRow(array $r): array
    {
        // Format tgl_daftar + jam_daftar dari Time_Daftar atau Tgl_Daftar + Jam_Daftar_Str
        $tglDaftar  = '';
        $jamDaftar  = '';
        if (!empty($r['Time_Daftar'])) {
            $parts     = explode(' ', trim($r['Time_Daftar']));
            $tglDaftar = $parts[0] ?? '';
            $jamDaftar = $parts[1] ?? '';
        } elseif (!empty($r['Tgl_Daftar'])) {
            $tglDaftar = substr($r['Tgl_Daftar'], 0, 10);
            $jamDaftar = $r['Jam_Daftar_Str'] ?? '';
        }

        return [
            'no_reg'       => $r['No_Reg']      ?? '',
            'no_mr'        => $r['No_MR']        ?? '',
            'nama_pasien'  => trim($r['Nama_Pasien'] ?? ''),
            'ket_bayar'    => trim($r['KET_BAYAR']   ?? ''),
            'nama_ruang'   => $r['Nama_Ruang']   ?? '',
            'nama_bangsal' => $r['Nama_Bangsal'] ?? '',
            'nama_kelas'   => $r['Nama_Kelas']   ?? '',
            'tgl_daftar'   => $tglDaftar,
            'jam_daftar'   => $jamDaftar,
            'keterangan'   => $r['Keterangan']   ?? '',   // status keterangan dari field, bukan dropdown
            'label'        => trim(($r['No_Reg'] ?? '') . ' — ' . trim($r['Nama_Pasien'] ?? '')),
        ];
    }

    private function mockPasien(string $search = ''): array
    {
        $data = [
            ['no_reg'=>'14-00041470','no_mr'=>'353245','nama_pasien'=>'LE WI BY',        'ket_bayar'=>'PRIBADI/MANDIRI',               'nama_ruang'=>'PERINA 02',     'nama_bangsal'=>'PERINA',         'nama_kelas'=>'NEONATUS', 'tgl_daftar'=>'2414-04-26','jam_daftar'=>'18:02:00','keterangan'=>'Observasi'],
            ['no_reg'=>'16-00018027','no_mr'=>'129451','nama_pasien'=>'KM ID TN',         'ket_bayar'=>'TANGGUNGAN INSTANSI/PERUSAHAAN','nama_ruang'=>'',              'nama_bangsal'=>'',               'nama_kelas'=>'',         'tgl_daftar'=>'6016-02-12','jam_daftar'=>'10:25:00','keterangan'=>'Observasi'],
            ['no_reg'=>'21-00017029','no_mr'=>'588414','nama_pasien'=>'HA TN',            'ket_bayar'=>'BPJS',                          'nama_ruang'=>'DELIMA A18 D',  'nama_bangsal'=>'DELIMA ATAS',    'nama_kelas'=>'KELAS III','tgl_daftar'=>'2121-02-25','jam_daftar'=>'09:00:00','keterangan'=>'Observasi'],
            // Data pakai format tahun normal untuk mock lokal
            ['no_reg'=>'569142',    'no_mr'=>'569142','nama_pasien'=>'ONGKI SAPUTRA, TN','ket_bayar'=>'BPJS',                          'nama_ruang'=>'PS ATAS 01',    'nama_bangsal'=>'PAHLAWAN ATAS',  'nama_kelas'=>'KELAS VIP','tgl_daftar'=>'2026-06-28','jam_daftar'=>'19:56:40','keterangan'=>'Antri Admisi'],
            ['no_reg'=>'REG001',    'no_mr'=>'813500','nama_pasien'=>'ELLY MAYA, NY',    'ket_bayar'=>'BPJS',                          'nama_ruang'=>'DAHLIA 2',      'nama_bangsal'=>'DAHLIA',         'nama_kelas'=>'Kelas 1',  'tgl_daftar'=>'2026-06-29','jam_daftar'=>'08:00:00','keterangan'=>'Belum Dapat Kamar'],
            ['no_reg'=>'REG002',    'no_mr'=>'575360','nama_pasien'=>'IDH SUBINGSEN, NY','ket_bayar'=>'BPJS',                          'nama_ruang'=>'MAWAR 3',       'nama_bangsal'=>'MAWAR',          'nama_kelas'=>'Kelas 2',  'tgl_daftar'=>'2026-06-29','jam_daftar'=>'10:30:00','keterangan'=>'Antri Admisi'],
            ['no_reg'=>'REG003',    'no_mr'=>'087220','nama_pasien'=>'RUSMINI, NY',      'ket_bayar'=>'Umum',                          'nama_ruang'=>'ANGGREK 1',     'nama_bangsal'=>'ANGGREK',        'nama_kelas'=>'Kelas 3',  'tgl_daftar'=>'2026-06-28','jam_daftar'=>'14:15:00','keterangan'=>'Sudah Masuk Kamar'],
            ['no_reg'=>'REG004',    'no_mr'=>'816302','nama_pasien'=>'PUSPA SARI, AN',   'ket_bayar'=>'BPJS',                          'nama_ruang'=>'ICU 2',         'nama_bangsal'=>'ICU',            'nama_kelas'=>'VIP',      'tgl_daftar'=>'2026-06-27','jam_daftar'=>'09:00:00','keterangan'=>'Sudah Masuk Kamar'],
            ['no_reg'=>'REG005',    'no_mr'=>'712405','nama_pasien'=>'BUDI SANTOSO, TN', 'ket_bayar'=>'Asuransi',                      'nama_ruang'=>'MAWAR 1',       'nama_bangsal'=>'MAWAR',          'nama_kelas'=>'Kelas 1',  'tgl_daftar'=>'2026-06-27','jam_daftar'=>'11:00:00','keterangan'=>'Belum Dapat Kamar'],
            ['no_reg'=>'REG006',    'no_mr'=>'654321','nama_pasien'=>'SRI WAHYUNI, NY',  'ket_bayar'=>'BPJS',                          'nama_ruang'=>'DAHLIA 3',      'nama_bangsal'=>'DAHLIA',         'nama_kelas'=>'Kelas 2',  'tgl_daftar'=>'2026-06-29','jam_daftar'=>'07:30:00','keterangan'=>'Antri Admisi'],
            ['no_reg'=>'REG007',    'no_mr'=>'789012','nama_pasien'=>'AHMAD FAUZI, TN',  'ket_bayar'=>'Umum',                          'nama_ruang'=>'ANGGREK 2',     'nama_bangsal'=>'ANGGREK',        'nama_kelas'=>'Kelas 3',  'tgl_daftar'=>'2026-06-28','jam_daftar'=>'16:00:00','keterangan'=>'Belum Diantar'],
            ['no_reg'=>'REG008',    'no_mr'=>'345678','nama_pasien'=>'DEWI RAHAYU, NY',  'ket_bayar'=>'BPJS',                          'nama_ruang'=>'MAWAR 2',       'nama_bangsal'=>'MAWAR',          'nama_kelas'=>'Kelas 1',  'tgl_daftar'=>'2026-06-29','jam_daftar'=>'09:15:00','keterangan'=>'Antri Admisi'],
            ['no_reg'=>'REG009',    'no_mr'=>'901234','nama_pasien'=>'HENDRA WIJAYA, TN','ket_bayar'=>'Asuransi',                      'nama_ruang'=>'ICU 1',         'nama_bangsal'=>'ICU',            'nama_kelas'=>'VIP',      'tgl_daftar'=>'2026-06-28','jam_daftar'=>'13:45:00','keterangan'=>'Sudah Masuk Kamar'],
            ['no_reg'=>'REG010',    'no_mr'=>'567890','nama_pasien'=>'SITI AMINAH, NY',  'ket_bayar'=>'BPJS',                          'nama_ruang'=>'DAHLIA 1',      'nama_bangsal'=>'DAHLIA',         'nama_kelas'=>'Kelas 2',  'tgl_daftar'=>'2026-06-27','jam_daftar'=>'15:00:00','keterangan'=>'Belum Dapat Kamar'],
        ];

        $data = array_map(fn($d) => array_merge($d, [
            'label' => ($d['no_reg'] ?? '') . ' — ' . ($d['nama_pasien'] ?? ''),
        ]), $data);

        if ($search === '') return $data;

        $s = strtolower($search);
        return array_values(array_filter($data, fn($d) =>
            str_contains(strtolower($d['no_reg'] ?? ''), $s) ||
            str_contains(strtolower($d['no_mr'] ?? ''), $s) ||
            str_contains(strtolower($d['nama_pasien'] ?? ''), $s)
        ));
    }
}
