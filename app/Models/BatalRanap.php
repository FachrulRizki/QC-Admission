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
        'keterangan_batal',
        'status_ok',
        'ketersediaan_kamar',
        'diagnosa',
        'note',
        'petugas',
    ];
}
