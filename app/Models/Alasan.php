<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Alasan extends Model
{
    use HasFactory;

    protected $table = 'qcw_alasans';

    protected $fillable = [
        'tanggal',
        'jam_input',
        'no_reg',
        'no_mr',
        'nama_pasien',
        'jaminan',
        'tgl_daftar',
        'jam_daftar',
        'nama_ruang',
        'nama_bangsal',
        'alasan',
        'catatan',
        'petugas',
    ];
}
