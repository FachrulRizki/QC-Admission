<?php

namespace App\Services\QcAdmission;

use App\Models\QualityControl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QualityControlService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = QualityControl::query()
            ->select(['id','no_mr','no_reg','nama_pasien','jaminan','tanggal','jam_input',
                      'tgl_daftar','jam_daftar','status_ket',
                      'petugas','status','status_ranap','edukasi_kamar','note',
                      'keluarga_pasien','ttd_keluarga_pasien',
                      'durasi_tunggu','created_at','updated_at'])
            ->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(fn($s) => $s
                ->where('no_mr',        'like', "%{$q}%")
                ->orWhere('no_reg',     'like', "%{$q}%")
                ->orWhere('nama_pasien','like', "%{$q}%")
                ->orWhere('petugas',    'like', "%{$q}%")
            );
        }

        if (! empty($filters['status']))    $query->where('status',   $filters['status']);
        if (! empty($filters['petugas']))   $query->where('petugas',  $filters['petugas']);
        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        // Sembunyikan QC yang sudah pindah ke Edukasi Lanjutan
        $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('qcw_edukasi_lanjutans')
                ->whereColumn('qcw_edukasi_lanjutans.quality_control_id', 'qcw_quality_controls.id');
        });

        $perPage = min((int) ($filters['per_page'] ?? 100), 500);
        return $query->paginate($perPage);
    }

    public function create(array $data): QualityControl
    {
        $data['status'] = 'Edukasi'; // selalu Edukasi saat entry
        return QualityControl::create($data);
    }

    public function findOrFail(int $id): QualityControl
    {
        return QualityControl::findOrFail($id);
    }

    public function update(int $id, array $data): QualityControl
    {
        $record = $this->findOrFail($id);
        unset($data['status']); // status tidak boleh diubah manual
        $record->update($data);
        return $record->fresh();
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
