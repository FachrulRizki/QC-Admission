<?php

namespace App\Services\QcAdmission;

use App\Models\Alasan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AlasanService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Alasan::query()->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(fn($s) => $s
                ->where('no_reg',      'like', "%{$q}%")
                ->orWhere('no_mr',     'like', "%{$q}%")
                ->orWhere('nama_pasien','like', "%{$q}%")
                ->orWhere('alasan',    'like', "%{$q}%")
                ->orWhere('petugas',   'like', "%{$q}%")
            );
        }

        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        $perPage = min((int) ($filters['per_page'] ?? 100), 500);
        return $query->paginate($perPage);
    }

    public function create(array $data): Alasan
    {
        return Alasan::create($data);
    }

    public function findOrFail(int $id): Alasan
    {
        return Alasan::findOrFail($id);
    }

    public function update(int $id, array $data): Alasan
    {
        $record = $this->findOrFail($id);
        $record->update($data);
        return $record->fresh();
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
