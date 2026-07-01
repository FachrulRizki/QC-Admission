<?php

namespace App\Console\Commands;

use App\Models\EdukasiLanjutan;
use App\Models\QualityControl;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEdukasiLanjutan extends Command
{
    protected $signature   = 'qc:process-edukasi-lanjutan';
    protected $description = 'Auto-pindahkan pasien QC yang sudah >= 2 jam ke Edukasi Lanjutan';

    public function handle(): int
    {
        $threshold = Carbon::now()->subHours(2);

        // Ambil semua QC dengan status Edukasi, dibuat >= 2 jam lalu,
        // yang belum punya record Edukasi Lanjutan
        $candidates = QualityControl::where('status', 'Edukasi')
            ->where('created_at', '<=', $threshold)
            ->whereDoesntHave('edukasiLanjutans')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada pasien yang perlu dipindahkan.');
            return self::SUCCESS;
        }

        $bulanMap = [
            1 => 'JANUARI',  2 => 'FEBRUARI', 3 => 'MARET',    4 => 'APRIL',
            5 => 'MEI',      6 => 'JUNI',     7 => 'JULI',     8 => 'AGUSTUS',
            9 => 'SEPTEMBER',10 => 'OKTOBER', 11 => 'NOVEMBER',12 => 'DESEMBER',
        ];

        $count = 0;
        foreach ($candidates as $qc) {
            try {
                $now = Carbon::now();

                // Hitung durasi sesungguhnya sejak created_at
                $durasiDetik = $qc->created_at->diffInSeconds($now);
                $h = str_pad(floor($durasiDetik / 3600), 2, '0', STR_PAD_LEFT);
                $m = str_pad(floor(($durasiDetik % 3600) / 60), 2, '0', STR_PAD_LEFT);
                $s = str_pad($durasiDetik % 60, 2, '0', STR_PAD_LEFT);
                $durasi = "{$h}:{$m}:{$s}";

                // Update durasi_tunggu di QC record
                $qc->update(['durasi_tunggu' => $durasi]);

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

                $count++;
                $this->info("✓ {$qc->nama_pasien} ({$qc->no_reg}) — durasi: {$durasi}");
                Log::info("[Edukasi Lanjutan] Auto-created — QC #{$qc->id} {$qc->nama_pasien}, durasi: {$durasi}");

            } catch (\Exception $e) {
                $this->error("✗ QC #{$qc->id}: " . $e->getMessage());
                Log::error("[Edukasi Lanjutan] Gagal create — QC #{$qc->id}: " . $e->getMessage());
            }
        }

        $this->info("Selesai. {$count} pasien dipindahkan ke Edukasi Lanjutan.");
        return self::SUCCESS;
    }
}
