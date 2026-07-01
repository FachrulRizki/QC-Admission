<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BatalRanap extends Model
{
    use HasFactory;

    protected $table = 'batal_ranaps';

    protected $fillable = [
        'tanggal',
        'jam_input',
        'no_reg',
        'no_mr',
        'tgl_daftar',
        'jam_daftar',
        'nama_pasien',
        'keterangan_batal',
        'status_ok',          // null = belum diverifikasi, 'Bedah' | 'Non Bedah'
        'status_closing',     // 'Siap Closing' | 'Belum Siap Closing'
        'ketersediaan_kamar',
        'diagnosa',
        'note',
        'petugas',
        'bed_id',
        'ruangan',
    ];
}
