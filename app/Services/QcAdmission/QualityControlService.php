<?php

namespace App\Services\QcAdmission;

use App\Models\QualityControl;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QualityControlService
{
    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = QualityControl::query()->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($sub) use ($q) {
                $sub->where('no_mr',        'like', "%{$q}%")
                    ->orWhere('no_reg',      'like', "%{$q}%")
                    ->orWhere('nama_pasien', 'like', "%{$q}%")
                    ->orWhere('petugas',     'like', "%{$q}%");
            });
        }

        if (! empty($filters['status']))    $query->where('status',   $filters['status']);
        if (! empty($filters['petugas']))   $query->where('petugas',  $filters['petugas']);
        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): QualityControl
    {
        // Status selalu Edukasi saat entry — auto-pindah ke Edukasi Lanjutan
        // dilakukan oleh scheduler 'qc:process-edukasi-lanjutan' setelah >= 2 jam
        $data['status'] = 'Edukasi';
        return QualityControl::create($data);
    }

    public function findOrFail(int $id): QualityControl
    {
        return QualityControl::findOrFail($id);
    }

    // public function update(int $id, array $data): QualityControl
    // {
    //     $record = $this->findOrFail($id);
    //     $data['status'] = 'Edukasi'; // tidak bisa diubah manual
    //     $record->update($data);
    //     return $record->fresh();
    // }

     public function update(int $id, array $data): QualityControl
    {
        $record = $this->findOrFail($id);
        unset($data['status']);
        $record->update($data);
        return $record->fresh();
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
