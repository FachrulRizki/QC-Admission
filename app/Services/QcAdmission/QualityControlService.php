<?php

namespace App\Services\QcAdmission;

use App\Models\EdukasiLanjutan;
use App\Models\QualityControl;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class QualityControlService
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function durasiToSeconds(?string $durasi): int
    {
        if (! $durasi) return 0;
        $parts = explode(':', $durasi);
        if (count($parts) !== 3) return 0;
        return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
    }

    /**
     * Jika status = 'Edukasi lanjutan' DAN durasi_tunggu >= 2 jam (7200 detik),
     * auto-create record Edukasi Lanjutan (jika belum ada untuk QC ini).
     */
    private function autoCreateEdukasiLanjutan(QualityControl $qc): void
    {
        if ($qc->status !== 'Edukasi lanjutan') return;
        if ($this->durasiToSeconds($qc->durasi_tunggu) < 7200) return;

        $exists = EdukasiLanjutan::where('quality_control_id', $qc->id)->exists();
        if ($exists) return;

        $bulanMap = [
            1 => 'JANUARI',  2 => 'FEBRUARI', 3 => 'MARET',    4 => 'APRIL',
            5 => 'MEI',      6 => 'JUNI',     7 => 'JULI',     8 => 'AGUSTUS',
            9 => 'SEPTEMBER',10 => 'OKTOBER', 11 => 'NOVEMBER',12 => 'DESEMBER',
        ];
        $now = Carbon::now();

        try {
            EdukasiLanjutan::create([
                'tanggal'             => $now->format('d/m/Y'),
                'no_mr'               => $qc->no_mr,
                'no_reg'              => $qc->no_reg,
                'nama_pasien'         => $qc->nama_pasien,
                'jaminan'             => $qc->jaminan,
                'bulan'               => $bulanMap[$now->month],
                'edukasi_kamar'       => $qc->edukasi_kamar,
                'note'                => $qc->note,
                'petugas'             => $qc->petugas,
                'keluarga_pasien'     => $qc->keluarga_pasien,
                'ttd_keluarga_pasien' => $qc->ttd_keluarga_pasien,
                'status'              => 'Menunggu',
                'quality_control_id'  => $qc->id,
            ]);
            Log::info("[QC] Auto Edukasi Lanjutan dibuat — QC #{$qc->id} ({$qc->nama_pasien}), durasi: {$qc->durasi_tunggu}");
        } catch (\Exception $e) {
            Log::error("[QC] Gagal auto-create Edukasi Lanjutan: " . $e->getMessage());
        }
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = QualityControl::query()->latest();

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($sub) use ($q) {
                $sub->where('no_mr',        'like', "%{$q}%")
                    ->orWhere('no_reg',      'like', "%{$q}%")
                    ->orWhere('nama_pasien', 'like', "%{$q}%")
                    ->orWhere('petugas',     'like', "%{$q}%");
            });
        }

        if (! empty($filters['status']))    $query->where('status',   $filters['status']);
        if (! empty($filters['petugas']))   $query->where('petugas',  $filters['petugas']);
        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): QualityControl
    {
        $record = QualityControl::create($data);
        $this->autoCreateEdukasiLanjutan($record);
        return $record;
    }

    public function findOrFail(int $id): QualityControl
    {
        return QualityControl::findOrFail($id);
    }

    public function update(int $id, array $data): QualityControl
    {
        $record = $this->findOrFail($id);
        $record->update($data);
        $record = $record->fresh();
        $this->autoCreateEdukasiLanjutan($record);
        return $record;
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
