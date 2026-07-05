<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'ket_bayar' => [
                'BPJS', 'Umum', 'Asuransi', 'Jasa Raharja',
                'BPJS Ketenagakerjaan', 'Gratis', 'Lainnya',
            ],
            'ruangan' => [
                'IGD Umum', 'IGD Bedah', 'IGD Anak', 'IGD Kebidanan',
                'Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'Ruang Flamboyan',
                'ICU', 'NICU', 'HCU', 'PICU', 'Ruang Bersalin', 'Ruang Perina', 'OK',
            ],
            'kelas' => [
                'Kelas VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3', 'Suite Room', 'VVIP',
            ],
            'bangsal' => [
                'MAWAR', 'ANGGREK', 'DAHLIA', 'FLAMBOYAN',
                'PAHLAWAN ATAS', 'PAHLAWAN BAWAH',
                'KEBIDANAN', 'ANAK', 'BEDAH', 'INTERNIS',
                'ICU', 'NICU', 'HCU',
            ],
            'keterangan_batal' => [
                'APS Alih RS Lain', 'APS Rawat Jalan',
                'Saran Alih RS Lain', 'Saran Konsul Poli',
                'Sisrute Tidak Dapat Kamar', 'Batal Rawat', 'Kamar Penuh',
                'Pasien Menolak', 'DPJP Tidak Setuju', 'Keluarga Menolak', 'Kondisi Membaik',
            ],
            'status_ok' => [
                'Bedah', 'Non Bedah',
            ],
            'ket_up_selling' => [
                'Naik Kelas', 'Perubahan Jaminan',
            ],
            'status_ket_qc' => [
                'Belum Dapat Kamar', 'Antri Kamar', 'Sudah Dapat Kamar',
            ],
            'note_kamar' => [
                'Kelas 1 Bedah Laki-laki', 'Kelas 2 Bedah Laki-laki', 'Kelas 3 Bedah Laki-laki',
                'Kelas 1 Bedah Perempuan', 'Kelas 2 Bedah Perempuan', 'Kelas 3 Bedah Perempuan',
                'Kelas 1 Internis Laki-laki', 'Kelas 2 Internis Laki-laki', 'Kelas 3 Internis Laki-laki',
                'Kelas 1 Internis Perempuan', 'Kelas 2 Internis Perempuan', 'Kelas 3 Internis Perempuan',
                'Kelas 1 Onkologi Laki-laki', 'Kelas 2 Onkologi Laki-laki', 'Kelas 3 Onkologi Laki-laki',
                'Kelas 1 Onkologi Perempuan', 'Kelas 2 Onkologi Perempuan', 'Kelas 3 Onkologi Perempuan',
                'Kelas 1 Kebidanan', 'Kelas 2 Kebidanan', 'Kelas 3 Kebidanan',
                'Kelas 1 Anak', 'Kelas 2 Anak', 'Kelas 3 Anak', 'Kelas VIP',
            ],
            'cara_masuk' => [
                'IGD', 'Poli', 'Rujukan', 'Langsung',
            ],
            'diagnosa' => [
                'Hipertensi', 'Diabetes Mellitus', 'Stroke', 'Gagal Jantung', 'ISPA',
                'Pneumonia', 'Appendisitis', 'Fraktur', 'Demam Berdarah', 'Typhoid',
                'Gastroenteritis', 'Anemia', 'Asma', 'Epilepsi', 'Lainnya',
            ],
            'jaminan' => [
                'BPJS', 'Umum', 'Asuransi', 'Jasa Raharja',
                'BPJS Ketenagakerjaan', 'Gratis', 'Lainnya',
            ],
        ];

        foreach ($defaults as $category => $items) {
            foreach ($items as $order => $item) {
                // upsert agar aman di-run ulang tanpa duplikasi
                DB::table('master_data')->upsert(
                    [
                        'category'   => $category,
                        'item'       => $item,
                        'sort_order' => $order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    ['category', 'item'],          // unique keys
                    ['sort_order', 'updated_at'],  // kolom yang di-update jika sudah ada
                );
            }
        }

        $this->command->info('MasterDataSeeder: ' . collect($defaults)->sum(fn($v) => count($v)) . ' items seeded.');
    }
}
