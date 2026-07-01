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
        'nama_pasien',
        'keterangan_batal',
        'status_ok',          // null = belum diverifikasi, 'Bedah' | 'Non Bedah'
        'ketersediaan_kamar',
        'diagnosa',
        'note',
        'petugas',
        'bed_id',
        'ruangan',
    ];
}
