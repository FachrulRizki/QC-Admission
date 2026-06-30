<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * MasterDataController
 * Menyediakan semua data dropdown hardcoded (master data) untuk frontend.
 * Tidak ada tabel DB — data ini statis dan dikontrol di sini.
 */
class MasterDataController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'ket_bayar'         => $this->ketBayar(),
            'ruangan'           => $this->ruangan(),
            'kelas'             => $this->kelas(),
            'bangsal'           => $this->bangsal(),
            'keterangan_batal'  => $this->keteranganBatal(),
            'status_ok'         => $this->statusOk(),
            'ket_up_selling'    => $this->ketUpSelling(),
            'status_ket_qc'     => $this->statusKetQc(),
            'note_kamar'        => $this->noteKamar(),
            'cara_masuk'        => $this->caraMasuk(),
        ]);
    }

    private function ketBayar(): array
    {
        return ['BPJS', 'Umum', 'Asuransi', 'Jasa Raharja', 'BPJS Ketenagakerjaan', 'Gratis', 'Lainnya'];
    }

    private function ruangan(): array
    {
        return [
            'IGD Umum', 'IGD Bedah', 'IGD Anak', 'IGD Kebidanan',
            'Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'Ruang Flamboyan',
            'ICU', 'NICU', 'HCU', 'PICU',
            'Ruang Bersalin', 'Ruang Perina', 'OK',
        ];
    }

    private function kelas(): array
    {
        return ['Kelas VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3', 'Suite Room', 'VVIP'];
    }

    private function bangsal(): array
    {
        return [
            'MAWAR', 'ANGGREK', 'DAHLIA', 'FLAMBOYAN',
            'PAHLAWAN ATAS', 'PAHLAWAN BAWAH',
            'KEBIDANAN', 'ANAK', 'BEDAH', 'INTERNIS',
            'ICU', 'NICU', 'HCU',
        ];
    }

    private function keteranganBatal(): array
    {
        return [
            'APS Alih RS Lain',
            'APS Rawat Jalan',
            'Saran Alih RS Lain',
            'Saran Konsul Poli',
            'Sisrute Tidak Dapat Kamar',
            'Batal Rawat',
            'Kamar Penuh',
            'Pasien Menolak',
            'DPJP Tidak Setuju',
            'Keluarga Menolak',
            'Kondisi Membaik',
        ];
    }

    private function statusOk(): array
    {
        return ['Bedah', 'Non Bedah'];
    }

    private function ketUpSelling(): array
    {
        return ['Naik Kelas', 'Perubahan Jaminan'];
    }

    private function statusKetQc(): array
    {
        return ['Belum Dapat Kamar', 'Antri Kamar', 'Sudah Dapat Kamar'];
    }

    private function noteKamar(): array
    {
        return [
            'Kelas 1 Bedah Laki-laki', 'Kelas 2 Bedah Laki-laki', 'Kelas 3 Bedah Laki-laki',
            'Kelas 1 Bedah Perempuan', 'Kelas 2 Bedah Perempuan', 'Kelas 3 Bedah Perempuan',
            'Kelas 1 Internis Laki-laki', 'Kelas 2 Internis Laki-laki', 'Kelas 3 Internis Laki-laki',
            'Kelas 1 Internis Perempuan', 'Kelas 2 Internis Perempuan', 'Kelas 3 Internis Perempuan',
            'Kelas 1 Onkologi Laki-laki', 'Kelas 2 Onkologi Laki-laki', 'Kelas 3 Onkologi Laki-laki',
            'Kelas 1 Onkologi Perempuan', 'Kelas 2 Onkologi Perempuan', 'Kelas 3 Onkologi Perempuan',
            'Kelas 1 Kebidanan', 'Kelas 2 Kebidanan', 'Kelas 3 Kebidanan',
            'Kelas 1 Anak', 'Kelas 2 Anak', 'Kelas 3 Anak',
            'Kelas VIP',
        ];
    }

    private function caraMasuk(): array
    {
        return ['IGD', 'Poli', 'Rujukan', 'Langsung'];
    }
}
