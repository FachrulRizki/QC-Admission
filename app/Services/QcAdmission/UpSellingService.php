<?php

namespace App\Services\QcAdmission;

use App\Models\UpSelling;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UpSellingService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = UpSelling::query()->latest();

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('no_reg', 'like', "%{$filters['search']}%")
                  ->orWhere('nama_pasien', 'like', "%{$filters['search']}%")
                  ->orWhere('petugas', 'like', "%{$filters['search']}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function create(array $data): UpSelling
    {
        return UpSelling::create($data);
    }

    public function findOrFail(int $id): UpSelling
    {
        return UpSelling::findOrFail($id);
    }

    public function update(int $id, array $data): UpSelling
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
