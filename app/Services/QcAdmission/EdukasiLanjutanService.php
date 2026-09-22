<?php

namespace App\Services\QcAdmission;

use App\Models\EdukasiLanjutan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EdukasiLanjutanService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = EdukasiLanjutan::query()->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(fn($s) => $s
                ->where('no_mr',        'like', "%{$q}%")
                ->orWhere('no_reg',     'like', "%{$q}%")
                ->orWhere('nama_pasien','like', "%{$q}%")
                ->orWhere('petugas',    'like', "%{$q}%")
            );
        }

        if (! empty($filters['status']))    $query->where('status', $filters['status']);
        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        $paginator = $query->paginate($filters['per_page'] ?? 20);

        // Enrich dengan info transfer dari RSUS
        $this->enrichWithTransferInfo($paginator->getCollection());

        // Jika tidak minta data yang sudah transfer, tandai saja (filter dilakukan di controller/frontend)
        // include_transferred = true → untuk view-data-input (tampilkan yang has_transfer)
        // include_transferred = false (default) → untuk halaman edukasi lanjutan (exclude yang has_transfer)
        $includeTransferred = filter_var($filters['include_transferred'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $includeTransferred) {
            // Exclude dari koleksi yang sudah punya lembar transfer
            $filtered = $paginator->getCollection()->filter(fn($r) => ! $r->has_transfer);
            $paginator->setCollection($filtered->values());
        } else {
            // Hanya yang sudah transfer
            $filtered = $paginator->getCollection()->filter(fn($r) => $r->has_transfer);
            $paginator->setCollection($filtered->values());
        }

        return $paginator;
    }

    /**
     * Enrich koleksi EdukasiLanjutan dengan info dari tabel RSUS:
     *  - has_transfer  : bool — apakah sudah ada lembar transfer (ASESMEN_TRANSFER_PASIEN)
     *  - keterangan    : string — 'Sudah Masuk Kamar' | 'Belum Diantar' | 'Belum Dapat Kamar' | null
     */
    private function enrichWithTransferInfo($collection): void
    {
        if ($collection->isEmpty()) return;

        if (! config('services.rsus_db_enabled', false)) {
            // Tidak ada koneksi RSUS — tandai semua has_transfer = false
            $collection->each(function ($r) {
                $r->has_transfer = false;
                $r->keterangan   = null;
            });
            return;
        }

        $noRegs = $collection->pluck('no_reg')->filter()->unique()->values()->toArray();
        if (empty($noRegs)) {
            $collection->each(function ($r) { $r->has_transfer = false; $r->keterangan = null; });
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($noRegs), '?'));
            $rows = DB::connection('rsus')->select("
                SELECT
                    P.No_Reg,
                    CASE
                        WHEN (
                            SELECT TOP 1 TglPindah
                            FROM dbo.ASESMEN_TRANSFER_PASIEN
                            WHERE No_Reg = P.No_Reg AND NamaPetugasMenerima <> ''
                        ) IS NOT NULL THEN 'Sudah Masuk Kamar'
                        WHEN (
                            SELECT TOP 1 Tgl_Mulai
                            FROM dbo.TR_KAMAR
                            WHERE No_Reg = P.No_Reg
                        ) IS NOT NULL THEN 'Belum Diantar'
                        ELSE 'Belum Dapat Kamar'
                    END AS Keterangan
                FROM dbo.PENDAFTARAN AS P
                WHERE P.No_Reg IN ({$placeholders})
            ", $noRegs);

            $map = collect($rows)->keyBy('No_Reg');

            $collection->each(function ($r) use ($map) {
                $info = $map->get($r->no_reg);
                $ket  = $info?->Keterangan ?? null;
                $r->has_transfer = ($ket === 'Sudah Masuk Kamar');
                $r->keterangan   = $ket;
            });
        } catch (\Exception $e) {
            Log::warning('EdukasiLanjutanService: gagal enrich dari RSUS', ['error' => $e->getMessage()]);
            $collection->each(function ($r) { $r->has_transfer = false; $r->keterangan = null; });
        }
    }

    public function create(array $data): EdukasiLanjutan
    {
        $data['status'] = $data['status'] ?? 'Menunggu';
        return EdukasiLanjutan::create($data);
    }

    public function findOrFail(int $id): EdukasiLanjutan
    {
        return EdukasiLanjutan::findOrFail($id);
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

    public function pending(array $filters = []): LengthAwarePaginator
    {
        $filters['status'] = 'Menunggu';
        return $this->paginate($filters);
    }
}
