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
    ];
}
