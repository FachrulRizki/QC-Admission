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
        $query = EdukasiLanjutan::query()->with('qualityControl')->latest();

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('no_mr',        'like', "%{$filters['search']}%")
                  ->orWhere('no_reg',     'like', "%{$filters['search']}%")
                  ->orWhere('nama_pasien','like', "%{$filters['search']}%")
                  ->orWhere('petugas',    'like', "%{$filters['search']}%");
            });
        }

        if (! empty($filters['month']))  $query->where('bulan', $filters['month']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['petugas'])) $query->where('petugas', $filters['petugas']);
        if (! empty($filters['year']))   $query->whereYear('created_at', $filters['year']);

        // Auto-sync status dari RSUS sebelum paginate jika RSUS aktif
        if (config('services.rsus_db_enabled', false)) {
            $this->syncStatusFromRsus();
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

    /**
     * Sync status Edukasi Lanjutan dari DB RSUS.
     *
     * Logic:
     * - Ambil semua edukasi lanjutan yang masih 'Menunggu' dan punya no_reg
     * - Query DB RSUS: cek apakah tgl_masuk_Kamar sudah terisi (pasien sudah dapat bed)
     * - Jika iya → set status = 'Selesai'
     *
     * Dipanggil saat paginate() jika RSUS_DB_ENABLED=true.
     * Juga bisa dipanggil manual via endpoint /api/edukasi-lanjutan/sync-rsus.
     */
    public function syncStatusFromRsus(): void
    {
        try {
            $pending = EdukasiLanjutan::where('status', 'Menunggu')
                ->whereNotNull('no_reg')
                ->get(['id', 'no_reg']);

            if ($pending->isEmpty()) return;

            $noRegs = $pending->pluck('no_reg')->toArray();

            // Query RSUS — cek pasien yang tgl_masuk_Kamar sudah terisi
            $sudahMasuk = DB::connection('rsus')->select("
                SELECT P.No_Reg
                FROM (
                    SELECT P.No_Reg,
                        (SELECT TOP(1) TglPindah
                         FROM dbo.ASESMEN_TRANSFER_PASIEN
                         WHERE No_Reg = P.No_Reg AND NamaPetugasMenerima <> '') AS tgl_masuk_Kamar
                    FROM dbo.PENDAFTARAN AS P
                    WHERE P.No_Reg IN ('" . implode("','", array_map('addslashes', $noRegs)) . "')
                ) AS L
                WHERE L.tgl_masuk_Kamar IS NOT NULL
            ");

            $sudahMasukNoRegs = collect($sudahMasuk)->pluck('No_Reg')->toArray();

            if (empty($sudahMasukNoRegs)) return;

            $updated = EdukasiLanjutan::where('status', 'Menunggu')
                ->whereIn('no_reg', $sudahMasukNoRegs)
                ->update(['status' => 'Selesai', 'updated_at' => now()]);

            if ($updated > 0) {
                Log::info("[EdukasiLanjutan] Sync RSUS: $updated record diupdate ke Selesai.");
            }
        } catch (\Exception $e) {
            // Jangan block request jika RSUS tidak bisa diakses
            Log::warning("[EdukasiLanjutan] Sync RSUS gagal: " . $e->getMessage());
        }
    }
}
