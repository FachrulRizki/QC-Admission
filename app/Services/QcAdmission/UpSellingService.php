<?php

namespace App\Services\QcAdmission;

use App\Models\UpSelling;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpSellingService
{
    // Kolom baru — graceful guard

    private const NEW_COLUMNS = ['status_ok', 'keterangan_ok'];

    private function hasNewColumns(): bool
    {
        static $checked = null;
        if ($checked !== null) return $checked;

        try {
            $checked = \Illuminate\Support\Facades\Schema::hasColumns('qcw_up_sellings', self::NEW_COLUMNS);
        } catch (\Exception) {
            $checked = false;
        }

        return $checked;
    }

    private function filterPayload(array $data): array
    {
        if ($this->hasNewColumns()) return $data;
        return array_diff_key($data, array_flip(self::NEW_COLUMNS));
    }

    // RSUS: cek jadwal operasi
    public function cekStatusBedah(string $noReg): array
    {
        if (! config('services.rsus_db_enabled', false)) {
            return ['status_ok' => 'NonBedah', 'keterangan_jadwal' => null];
        }

        try {
            $row = DB::connection('rsus')
                ->table('JADWAL_OPERASI')
                ->where('No_Reg', $noReg)
                ->orderByDesc('Tanggal')
                ->first(['No_Jadwal', 'Tindakan', 'Keterangan', 'DPJP', 'Tanggal']);

            if ($row) {
                return [
                    'status_ok'         => 'Bedah',
                    'keterangan_jadwal' => trim(
                        implode(' | ', array_filter([
                            $row->Tindakan   ?? null,
                            $row->Keterangan ?? null,
                            $row->DPJP       ? "dr. {$row->DPJP}" : null,
                        ]))
                    ) ?: null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('UpSellingService::cekStatusBedah RSUS failed', [
                'no_reg' => $noReg,
                'error'  => $e->getMessage(),
            ]);
        }

        return ['status_ok' => 'NonBedah', 'keterangan_jadwal' => null];
    }

    // Alert notifikasi
    public function alertBedahBelumKeterangan(): array
    {
        if (! $this->hasNewColumns()) return [];

        return \Illuminate\Support\Facades\Cache::remember('up_selling_alert_bedah', 60, function () {
            return UpSelling::query()
                ->where('status_ok', 'Bedah')
                ->where(fn($q) => $q
                    ->whereNull('keterangan_ok')
                    ->orWhere('keterangan_ok', '')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(['id', 'no_reg', 'no_mr', 'nama_pasien', 'petugas', 'tanggal', 'keterangan_ok'])
                ->toArray();
        });
    }

    public function clearAlertCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('up_selling_alert_bedah');
    }

    // CRUD
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = UpSelling::query()->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(fn($s) => $s
                ->where('no_reg',      'like', "%{$q}%")
                ->orWhere('nama_pasien', 'like', "%{$q}%")
                ->orWhere('petugas',   'like', "%{$q}%")
            );
        }

        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);
        if (! empty($filters['status']))    $query->where('status', $filters['status']);

        $perPage = min((int) ($filters['per_page'] ?? 100), 500);
        return $query->paginate($perPage);
    }

    public function create(array $data): UpSelling
    {
        return UpSelling::create($this->filterPayload($data));
    }

    public function findOrFail(int $id): UpSelling
    {
        return UpSelling::findOrFail($id);
    }

    public function update(int $id, array $data): UpSelling
    {
        $record = $this->findOrFail($id);
        $record->update($this->filterPayload($data));
        $this->clearAlertCache();
        return $record->fresh();
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
