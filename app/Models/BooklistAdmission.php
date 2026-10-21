<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BooklistAdmission extends Model
{
    use HasFactory;

    protected $table = 'qcw_booklist_admissions';

    protected $fillable = [
        'tanggal_booklist',
        'no_reg',
        'no_mr',
        'nama_pasien',
        'no_hp',
        'jaminan',
        'kelas',
        'dpjp',
        'diagnosa',
        'tanggal_op',
        'status_pasien',
        'catatan',
        'petugas',
        // Status tindak lanjut
        'sudah_dihubungi',
        'waktu_dihubungi',
        'catatan_dihubungi',
        // Trigger: pasien sudah dapat kamar (dari RSUS) → keluar dari list booklist
        'waktu_dapat_kamar',
        'nama_bangsal',
        'nama_ruang',
    ];

    protected $casts = [
        'waktu_dapat_kamar' => 'datetime:d/m/Y H:i',
        'sudah_dihubungi' => 'boolean',
        'waktu_dihubungi' => 'datetime:d/m/Y H:i',
    ];

    protected $appends = ['sudah_dapat_kamar'];

    /** Sudah dapat kamar = waktu_dapat_kamar terisi (tidak ada kolom flag terpisah). */
    public function getSudahDapatKamarAttribute(): bool
    {
        return ! empty($this->attributes['waktu_dapat_kamar']);
    }
}
