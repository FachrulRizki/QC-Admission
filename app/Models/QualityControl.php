<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QualityControl extends Model
{
    use HasFactory;

    protected $table = 'quality_controls';

    public function edukasiLanjutans()
    {
        return $this->hasMany(\App\Models\EdukasiLanjutan::class, 'quality_control_id');
    }

    protected $fillable = [
        'tanggal',
        'jam_input',
        'tgl_daftar',
        'jam_daftar',
        'no_mr',
        'no_reg',
        'nama_pasien',
        'jaminan',
        'status_ket',
        'edukasi_kamar',
        'durasi_tunggu',
        'note',
        'petugas',
        'status',
        'status_ranap',
        'ranap_at',
        'keluarga_pasien',
        'ttd_keluarga_pasien',
    ];

    protected $casts = [
        'ranap_at' => 'datetime',
    ];
}
