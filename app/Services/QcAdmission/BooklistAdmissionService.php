<?php

namespace App\Services\QcAdmission;

use App\Models\ActivityLog;
use App\Models\BooklistAdmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BooklistAdmissionService
{
    // Kolom status "sudah dapat kamar" — perlu dibuat DBA; tanpa kolom ini fitur dilewati
    private const KAMAR_COLUMNS = ['waktu_dapat_kamar', 'nama_bangsal', 'nama_ruang'];

    private const SYNC_LOCK_KEY = 'booklist_sync_dapat_kamar';
    private const SYNC_INTERVAL = 60;  
    private const SYNC_DAYS     = 90;  

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = BooklistAdmission::query()->latest();

        // Pasien yang sudah dapat kamar hilang dari list (riwayatnya ada di History Pasien)
        if ($this->hasKamarColumns()) {
            $this->syncDapatKamar();
            if (! filter_var($filters['include_dapat_kamar'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNull('waktu_dapat_kamar');
            }
        }

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

        $perPage = min((int) ($filters['per_page'] ?? 100), 2000);

        return $query->paginate($perPage);
    }

    public function hasKamarColumns(): bool
    {
        return Cache::remember('booklist_has_kamar_columns', 300, function () {
            try {
                return Schema::hasColumns('qcw_booklist_admissions', self::KAMAR_COLUMNS);
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function syncDapatKamar(bool $force = false): int
    {
        if (! config('services.rsus_db_enabled', false)) return 0;
        if (! $force && ! Cache::add(self::SYNC_LOCK_KEY, true, self::SYNC_INTERVAL)) return 0;

        $pending = BooklistAdmission::query()
            ->whereNull('waktu_dapat_kamar')
            ->where('created_at', '>=', now()->subDays(self::SYNC_DAYS))
            ->where(fn($q) => $q->whereNotNull('no_reg')->orWhereNotNull('no_mr'))
            ->get(['id', 'no_reg', 'no_mr', 'nama_pasien', 'created_at']);

        $updated = 0;

        // 4 parameter per baris × 400 = 1600 → di bawah limit 2100 parameter SQL Server
        foreach ($pending->chunk(400) as $chunk) {
            $values = implode(',', array_fill(0, $chunk->count(),
                '(?, CAST(? AS varchar(30)), CAST(? AS varchar(30)), CONVERT(date, ?, 112))'));

            $params = [];
            foreach ($chunk as $b) {
                array_push($params, (int) $b->id, (string) $b->no_reg, (string) $b->no_mr, $b->created_at->format('Ymd'));
            }

            try {
                // CAST varchar + UNION ALL (bukan OR) → index No_Reg & No_MR terpakai (24 dtk → 0,05 dtk)
                $rows = DB::connection('rsus')->select("
                    SELECT B.id, X.No_Reg, X.Nama_Ruang, X.Nama_Bangsal
                    FROM (VALUES {$values}) AS B(id, no_reg, no_mr, tgl)
                    CROSS APPLY (
                        SELECT TOP 1 P.No_Reg, R.Nama_Ruang, BG.Nama_Bangsal
                        FROM (
                            SELECT No_Reg, Tanggal FROM dbo.PENDAFTARAN WHERE No_Reg = B.no_reg
                            UNION ALL
                            SELECT No_Reg, Tanggal FROM dbo.PENDAFTARAN WHERE No_MR = B.no_mr AND Tanggal >= B.tgl
                        ) AS P
                        INNER JOIN dbo.TR_KAMAR AS K ON K.No_Reg = P.No_Reg
                        LEFT JOIN dbo.M_RUANG AS R ON R.Kode_Ruang = K.Kode_Ruang
                        LEFT JOIN dbo.M_BANGSAL AS BG ON BG.Kode_Bangsal = R.Kode_Bangsal
                        ORDER BY P.Tanggal DESC
                    ) AS X
                ", $params);
            } catch (\Throwable $e) {
                Log::warning('Booklist syncDapatKamar: gagal cek RSUS.', ['error' => $e->getMessage()]);
                return $updated;
            }

            $byId = $chunk->keyBy('id');
            foreach ($rows as $row) {
                $booklist = $byId->get((int) $row->id);
                if (! $booklist) continue;

                $booklist->update([
                    'waktu_dapat_kamar' => now(),
                    'nama_bangsal'      => trim((string) ($row->Nama_Bangsal ?? '')) ?: null,
                    'nama_ruang'       => trim((string) ($row->Nama_Ruang ?? '')) ?: null,
                ]);
                $updated++;

                ActivityLog::record(
                    'booklist-admission',
                    'dapat-kamar',
                    "Booklist selesai — {$booklist->nama_pasien} sudah dapat kamar ({$row->Nama_Bangsal} / {$row->Nama_Ruang})",
                    ['booklist_id' => $booklist->id, 'no_reg' => $row->No_Reg],
                );
            }
        }

        if ($updated) {
            Cache::forget('history_pasien_index');
        }

        return $updated;
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

    /**
     * Tandai pasien sudah/belum dihubungi.
     * Jika sudah_dihubungi = true → waktu_dihubungi diisi sekarang.
     * Jika false → waktu_dihubungi dikosongkan.
     */
    public function markDihubungi(int $id, array $data): BooklistAdmission
    {
        $record = $this->findOrFail($id);

        $record->update([
            'sudah_dihubungi'   => $data['sudah_dihubungi'],
            'waktu_dihubungi'   => $data['sudah_dihubungi'] ? now() : null,
            'catatan_dihubungi' => array_key_exists('catatan_dihubungi', $data)
                ? $data['catatan_dihubungi']
                : $record->catatan_dihubungi,
        ]);

        return $record->fresh();
    }
}
