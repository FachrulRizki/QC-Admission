<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UpSelling extends Model
{
    use HasFactory;

    protected $table = 'qcw_up_sellings';

    protected $fillable = [
        'tanggal',
        'jam_input',
        'no_reg',
        'no_mr',
        'tgl_daftar',
        'nama_pasien',
        'jaminan',
        'nama_ruang',
        'nama_bangsal',
        'kelas',
        'rekomendasi_kelas',
        'kelas_diambil',
        'alasan',          // Ket_Up_Selling: Naik Kelas / Perubahan Jaminan
        'petugas',
        'status',
        'note',            // notes — keterangan bebas
    ];
}
