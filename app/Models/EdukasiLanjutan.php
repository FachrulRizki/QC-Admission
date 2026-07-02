<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdukasiLanjutan extends Model
{
    use HasFactory;

    protected $table = 'edukasi_lanjutans';

    protected $fillable = [
        'tanggal',
        'no_mr',
        'no_reg',
        'nama_pasien',
        'jaminan',
        'bulan',
        'edukasi_kamar',
        'note',
        'petugas',
        'keluarga_pasien',
        'ttd_keluarga_pasien',
        'status',
        'status_ranap',
        'ranap_at',
        'quality_control_id',
    ];

    protected $casts = [
        'ranap_at' => 'datetime',
    ];

    public function qualityControl(): BelongsTo
    {
        return $this->belongsTo(QualityControl::class, 'quality_control_id');
    }
}
