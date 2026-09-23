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
        $query = EdukasiLanjutan::query()
            ->select(['id','no_mr','no_reg','nama_pasien','jaminan','tanggal','bulan',
                      'petugas','keluarga_pasien','status','status_ranap','ranap_at',
                      'edukasi_kamar','note','quality_control_id','created_at','updated_at'])
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

        if (! empty($filters['status']))    $query->where('status', $filters['status']);
        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        // Batasi per_page maksimal 500 untuk cegah query besar
        $perPage = min((int) ($filters['per_page'] ?? 50), 500);
        $paginator = $query->paginate($perPage);

        // Enrich dengan info transfer hanya jika ada data
        $this->enrichWithTransferInfo($paginator->getCollection());

        $includeTransferred = filter_var($filters['include_transferred'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $includeTransferred) {
            $filtered = $paginator->getCollection()->filter(fn($r) => ! $r->has_transfer);
            $paginator->setCollection($filtered->values());
        } else {
            $filtered = $paginator->getCollection()->filter(fn($r) => $r->has_transfer);
            $paginator->setCollection($filtered->values());
        }

        return $paginator;
    }

    /**
     * Dipakai oleh View Data Input agar tidak perlu 2x request ke API.
     */
    public function allSplit(array $filters = []): array
    {
        $query = EdukasiLanjutan::query()
            ->select(['id','no_mr','no_reg','nama_pasien','jaminan','tanggal','bulan',
                      'petugas','keluarga_pasien','status','status_ranap','ranap_at',
                      'edukasi_kamar','note','quality_control_id','created_at','updated_at'])
            ->latest();

        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        $perPage = min((int) ($filters['per_page'] ?? 300), 1000);
        $collection = $query->limit($perPage)->get();

        // Satu kali enrich RSUS untuk kedua set
        $this->enrichWithTransferInfo($collection);

        return [
            'active'      => $collection->filter(fn($r) => ! $r->has_transfer)->values(),
            'transferred' => $collection->filter(fn($r) => $r->has_transfer)->values(),
        ];
    }

    /**
     * Menggunakan single batch query
     */
    private function enrichWithTransferInfo($collection): void
    {
        if ($collection->isEmpty()) return;

        if (! config('services.rsus_db_enabled', false)) {
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

            // Single query dengan LEFT JOIN — jauh lebih cepat dari correlated subqueries
            $rows = DB::connection('rsus')->select("
                SELECT
                    P.No_Reg,
                    CASE
                        WHEN T.No_Reg IS NOT NULL AND T.NamaPetugasMenerima <> '' THEN 'Sudah Masuk Kamar'
                        WHEN K.No_Reg IS NOT NULL THEN 'Belum Diantar'
                        ELSE 'Belum Dapat Kamar'
                    END AS Keterangan
                FROM dbo.PENDAFTARAN AS P
                LEFT JOIN (
                    SELECT No_Reg, MAX(NamaPetugasMenerima) AS NamaPetugasMenerima
                    FROM dbo.ASESMEN_TRANSFER_PASIEN
                    WHERE No_Reg IN ({$placeholders})
                    GROUP BY No_Reg
                ) AS T ON T.No_Reg = P.No_Reg
                LEFT JOIN (
                    SELECT No_Reg, MIN(Tgl_Mulai) AS Tgl_Mulai
                    FROM dbo.TR_KAMAR
                    WHERE No_Reg IN ({$placeholders})
                    GROUP BY No_Reg
                ) AS K ON K.No_Reg = P.No_Reg
                WHERE P.No_Reg IN ({$placeholders})
            ", array_merge($noRegs, $noRegs, $noRegs));

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
