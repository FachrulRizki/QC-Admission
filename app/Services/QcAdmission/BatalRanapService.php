<?php

namespace App\Services\QcAdmission;

use App\Models\BatalRanap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BatalRanapService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = BatalRanap::query()->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(fn($s) => $s
                ->where('no_reg',  'like', "%{$q}%")
                ->orWhere('petugas', 'like', "%{$q}%")
                ->orWhere('diagnosa','like', "%{$q}%")
            );
        }

        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);
        if (! empty($filters['status_ok'])) $query->where('status_ok', $filters['status_ok']);

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function create(array $data): BatalRanap
    {
        return BatalRanap::create($data);
    }

    public function findOrFail(int $id): BatalRanap
    {
        return BatalRanap::findOrFail($id);
    }

    public function update(int $id, array $data): BatalRanap
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
