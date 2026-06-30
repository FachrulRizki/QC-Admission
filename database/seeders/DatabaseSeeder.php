<?php

namespace Database\Seeders;

use App\Models\BatalRanap;
use App\Models\EdukasiLanjutan;
use App\Models\QualityControl;
use App\Models\UpSelling;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // ── Master data ─────────────────────────────────────────────────────────
    private array $petugas = [
        'Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum', 'Abdul Hayyi',
    ];

    private array $patients = [
        ['no_mr' => '813500', 'no_reg' => 'REG001', 'nama' => 'ELLY MAYA, NY',      'jaminan' => 'BPJS'],
        ['no_mr' => '575360', 'no_reg' => 'REG002', 'nama' => 'IDH SUBINGSEN, NY',  'jaminan' => 'BPJS'],
        ['no_mr' => '087220', 'no_reg' => 'REG003', 'nama' => 'RUSMINI, NY',        'jaminan' => 'Umum'],
        ['no_mr' => '816302', 'no_reg' => 'REG004', 'nama' => 'PUSPA SARI, AN',     'jaminan' => 'BPJS'],
        ['no_mr' => '712405', 'no_reg' => 'REG005', 'nama' => 'BUDI SANTOSO, TN',   'jaminan' => 'Asuransi'],
        ['no_mr' => '654321', 'no_reg' => 'REG006', 'nama' => 'SRI WAHYUNI, NY',    'jaminan' => 'BPJS'],
        ['no_mr' => '789012', 'no_reg' => 'REG007', 'nama' => 'AHMAD FAUZI, TN',    'jaminan' => 'Umum'],
        ['no_mr' => '345678', 'no_reg' => 'REG008', 'nama' => 'DEWI RAHAYU, NY',    'jaminan' => 'BPJS'],
        ['no_mr' => '901234', 'no_reg' => 'REG009', 'nama' => 'HENDRA WIJAYA, TN',  'jaminan' => 'Asuransi'],
        ['no_mr' => '567890', 'no_reg' => 'REG010', 'nama' => 'SITI AMINAH, NY',    'jaminan' => 'BPJS'],
    ];

    public function run(): void
    {
        // ── Users ──────────────────────────────────────────────────────────
        $users = [
            ['name' => 'Administrator',  'username' => 'admin',      'email' => 'admin@rsud.local',      'password' => 'Admin@1234',   'role' => 'admin'],
            ['name' => 'Nurul Hidayah',  'username' => 'nurul',      'email' => 'nurul@rsud.local',      'password' => 'Petugas@1234', 'role' => 'petugas'],
            ['name' => 'AYU Putri Anisa','username' => 'ayu.putri',  'email' => 'ayu@rsud.local',        'password' => 'Petugas@1234', 'role' => 'petugas'],
            ['name' => 'Reskim Maulana', 'username' => 'reskim',     'email' => 'reskim@rsud.local',     'password' => 'Petugas@1234', 'role' => 'petugas'],
            ['name' => 'Supervisor QC',  'username' => 'supervisor', 'email' => 'supervisor@rsud.local', 'password' => 'Super@1234',   'role' => 'supervisor'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['username' => $u['username']],
                [
                    'name'       => $u['name'],
                    'email'      => $u['email'],
                    'password'   => Hash::make($u['password']),
                    'role'       => $u['role'],
                    'login_type' => 'local',
                ]
            );
        }

        $this->command->info('✓ Users seeded (' . count($users) . ' akun)');

        // ── Quality Control ────────────────────────────────────────────────
        $statuses      = ['Edukasi', 'Edukasi lanjutan', 'Masuk'];
        $statusKets    = ['Belum Dapat Kamar', 'Antri Kamar', 'Sudah Dapat Kamar'];
        $edukasiKamars = ['', 'Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'ICU', 'NICU'];
        $notes         = ['Pasien mengerti', 'Keluarga hadir', 'Sudah menjelaskan kelas', 'Dirujuk'];
        $keluargas     = ['Bambang', 'Siti', 'Andi', 'Rini', 'Hendra', 'Dewi'];

        $qcRecords = [];
        for ($i = 0; $i < 20; $i++) {
            $p         = $this->patients[$i % count($this->patients)];
            $petugas   = $this->petugas[$i % count($this->petugas)];
            $status    = $statuses[$i % count($statuses)];
            $createdAt = Carbon::now()->subDays(rand(0, 7))->subHours(rand(0, 8));
            $pad       = fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT);

            $jam = $pad($createdAt->hour) . '.' . $pad($createdAt->minute) . '.' . $pad($createdAt->second);
            $tgl = $pad($createdAt->day) . '/' . $pad($createdAt->month) . '/' . $createdAt->year . ', ' . $jam;

            $dSec = rand(600, 7200);
            $durasi = $pad(floor($dSec / 3600)) . ':' . $pad(floor(($dSec % 3600) / 60)) . ':' . $pad($dSec % 60);

            $qc = QualityControl::create([
                'tanggal'             => $tgl,
                'jam_input'           => $jam,
                'tgl_daftar'          => $tgl,
                'jam_daftar'          => $jam,
                'no_mr'               => $p['no_mr'],
                'no_reg'              => $p['no_reg'] . str_pad($i, 2, '0', STR_PAD_LEFT),
                'nama_pasien'         => $p['nama'],
                'jaminan'             => $p['jaminan'],
                'status_ket'          => $statusKets[$i % count($statusKets)],
                'edukasi_kamar'       => $edukasiKamars[$i % count($edukasiKamars)],
                'durasi_tunggu'       => $durasi,
                'note'                => $notes[$i % count($notes)],
                'petugas'             => $petugas,
                'status'              => $status,
                'keluarga_pasien'     => $keluargas[$i % count($keluargas)],
                'ttd_keluarga_pasien' => null,
                'created_at'          => $createdAt,
                'updated_at'          => $createdAt,
            ]);

            $qcRecords[] = $qc;
        }

        $this->command->info('✓ Quality Control seeded (' . count($qcRecords) . ' record)');

        // ── Edukasi Lanjutan — otomatis dari QC "Edukasi lanjutan" ─────────
        $bulanMap = [
            1=>'JANUARI',2=>'FEBRUARI',3=>'MARET',4=>'APRIL',5=>'MEI',6=>'JUNI',
            7=>'JULI',8=>'AGUSTUS',9=>'SEPTEMBER',10=>'OKTOBER',11=>'NOVEMBER',12=>'DESEMBER',
        ];
        $pad = fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT);
        $eduCount = 0;

        foreach ($qcRecords as $qc) {
            if ($qc->status === 'Edukasi lanjutan') {
                $t = Carbon::parse($qc->created_at)->addHours(2);
                EdukasiLanjutan::create([
                    'tanggal'             => $pad($t->day) . '/' . $pad($t->month) . '/' . $t->year,
                    'no_mr'               => $qc->no_mr,
                    'no_reg'              => $qc->no_reg,
                    'nama_pasien'         => $qc->nama_pasien,
                    'jaminan'             => $qc->jaminan,
                    'bulan'               => $bulanMap[$t->month],
                    'edukasi_kamar'       => $qc->edukasi_kamar,
                    'note'                => $qc->note,
                    'petugas'             => $qc->petugas,
                    'keluarga_pasien'     => $qc->keluarga_pasien,
                    'ttd_keluarga_pasien' => null,
                    'status'              => 'Menunggu',
                    'quality_control_id'  => $qc->id,
                    'created_at'          => $t,
                    'updated_at'          => $t,
                ]);
                $eduCount++;
            }
        }

        $this->command->info("✓ Edukasi Lanjutan seeded ($eduCount record)");

        // ── Batal Ranap ────────────────────────────────────────────────────
        $keterangans = ['Kamar Penuh','Pasien Menolak','DPJP Tidak Setuju','Keluarga Menolak','Kondisi Membaik'];
        $statusOks   = ['OK','Pending','Ditolak'];
        $ruangans    = ['Ruang Mawar','Ruang Anggrek','ICU','NICU','Ruang Dahlia'];

        for ($i = 0; $i < 10; $i++) {
            $p         = $this->patients[$i % count($this->patients)];
            $petugas   = $this->petugas[$i % count($this->petugas)];
            $createdAt = Carbon::now()->subDays(rand(0, 5))->subHours(rand(0, 6));
            $pad2      = fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT);
            $jam       = $pad2($createdAt->hour) . '.' . $pad2($createdAt->minute) . '.' . $pad2($createdAt->second);
            $tgl       = $pad2($createdAt->day) . '/' . $pad2($createdAt->month) . '/' . $createdAt->year . ', ' . $jam;

            BatalRanap::create([
                'tanggal'            => $tgl,
                'jam_input'          => $jam,
                'no_reg'             => $p['no_reg'] . 'BR' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'nama_pasien'        => $p['nama'],
                'keterangan_batal'   => $keterangans[$i % count($keterangans)],
                'status_ok'          => $statusOks[$i % count($statusOks)],
                'ketersediaan_kamar' => $i % 3 === 0 ? '2 kamar tersedia' : null,
                'diagnosa'           => ['Hipertensi','Diabetes','Stroke','Gagal Jantung','ISPA'][$i % 5],
                'note'               => $i % 2 === 0 ? 'Sudah konfirmasi keluarga' : null,
                'petugas'            => $petugas,
                'ruangan'            => $ruangans[$i % count($ruangans)],
                'created_at'         => $createdAt,
                'updated_at'         => $createdAt,
            ]);
        }

        $this->command->info('✓ Batal Ranap seeded (10 record)');

        // ── Up Selling ─────────────────────────────────────────────────────
        $kelasList  = ['VIP','Kelas 1','Kelas 2','Kelas 3'];
        $alasanList = ['Budget terbatas','Tidak ada kamar kelas lebih tinggi','Keinginan keluarga','Rekomendasi dokter'];
        $upStatus   = ['Berhasil','Tidak Berhasil','Pending'];

        for ($i = 0; $i < 10; $i++) {
            $p         = $this->patients[$i % count($this->patients)];
            $petugas   = $this->petugas[$i % count($this->petugas)];
            $createdAt = Carbon::now()->subDays(rand(0, 7))->subHours(rand(0, 5));
            $pad3      = fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT);
            $jam       = $pad3($createdAt->hour) . '.' . $pad3($createdAt->minute) . '.' . $pad3($createdAt->second);
            $tgl       = $pad3($createdAt->day) . '/' . $pad3($createdAt->month) . '/' . $createdAt->year . ', ' . $jam;

            UpSelling::create([
                'tanggal'           => $tgl,
                'jam_input'         => $jam,
                'no_reg'            => $p['no_reg'] . 'UP' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'nama_pasien'       => $p['nama'],
                'jaminan'           => $p['jaminan'],
                'rekomendasi_kelas' => $kelasList[$i % count($kelasList)],
                'kelas_diambil'     => $kelasList[($i + 1) % count($kelasList)],
                'alasan'            => $alasanList[$i % count($alasanList)],
                'petugas'           => $petugas,
                'status'            => $upStatus[$i % count($upStatus)],
                'note'              => null,
                'created_at'        => $createdAt,
                'updated_at'        => $createdAt,
            ]);
        }

        $this->command->info('✓ Up Selling seeded (10 record)');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('  Akun Login:');
        $this->command->info('  admin      / Admin@1234');
        $this->command->info('  nurul      / Petugas@1234');
        $this->command->info('  supervisor / Super@1234');
        $this->command->info('═══════════════════════════════════════');
    }
}
