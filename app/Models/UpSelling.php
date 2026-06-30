<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UpSelling extends Model
{
    use HasFactory;

    protected $table = 'up_sellings';

    protected $fillable = [
        'tanggal',
        'jam_input',
        'no_reg',
        'nama_pasien',
        'jaminan',
        'rekomendasi_kelas',
        'kelas_diambil',
        'alasan',
        'petugas',
        'status',
        'note',
    ];
}
