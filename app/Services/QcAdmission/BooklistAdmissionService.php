<?php

namespace App\Services\QcAdmission;

use App\Models\BooklistAdmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BooklistAdmissionService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = BooklistAdmission::query()->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(fn($s) => $s
                ->where('no_reg',      'like', "%{$q}%")
                ->orWhere('no_mr',     'like', "%{$q}%")
                ->orWhere('nama_pasien','like', "%{$q}%")
                ->orWhere('dpjp',      'like', "%{$q}%")
                ->orWhere('diagnosa',  'like', "%{$q}%")
            );
        }

        if (! empty($filters['status_pasien'])) {
            $query->where('status_pasien', $filters['status_pasien']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 100), 9999);

        return $query->paginate($perPage);
    }

    public function create(array $data): BooklistAdmission
    {
        return BooklistAdmission::create($data);
    }

    public function findOrFail(int $id): BooklistAdmission
    {
        return BooklistAdmission::findOrFail($id);
    }

    public function update(int $id, array $data): BooklistAdmission
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
