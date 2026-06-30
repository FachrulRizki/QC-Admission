<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EdukasiLanjutan extends Model
{
    use HasFactory;

    protected $table = 'edukasi_lanjutans';

    protected $fillable = [
        'tanggal',
        'no_mr',
        'nama_pasien',
        'bulan',
        'catatan',
        'petugas',
    ];
}
