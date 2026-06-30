<?php

namespace App\Services\QcAdmission;

use App\Models\EdukasiLanjutan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EdukasiLanjutanService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = EdukasiLanjutan::query()->with('qualityControl')->latest();

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('no_mr',       'like', "%{$filters['search']}%")
                  ->orWhere('nama_pasien', 'like', "%{$filters['search']}%")
                  ->orWhere('petugas',    'like', "%{$filters['search']}%");
            });
        }

        if (! empty($filters['month'])) {
            $query->where('bulan', $filters['month']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['year'])) {
            $query->whereYear('created_at', $filters['year']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): EdukasiLanjutan
    {
        return EdukasiLanjutan::create($data);
    }

    public function findOrFail(int $id): EdukasiLanjutan
    {
        return EdukasiLanjutan::with('qualityControl')->findOrFail($id);
    }

    public function update(int $id, array $data): EdukasiLanjutan
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
