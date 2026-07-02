<?php

namespace App\Console\Commands;

use App\Models\EdukasiLanjutan;
use App\Models\QualityControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessEdukasiLanjutan extends Command
{
    protected $signature = 'qc:process-edukasi-lanjutan';

    protected $description = 'Pindahkan data QC berstatus Edukasi yang sudah >= 2 jam ke tabel edukasi_lanjutans';

    public function handle(): int
    {
        $cutoff = now()->subHours(2);

        $records = QualityControl::query()
            ->where('status', 'Edukasi')
            ->where('created_at', '<=', $cutoff)
            // jaga-jaga kalau command sempat jalan dobel sebelum status sempat berubah
            ->whereDoesntHave('edukasiLanjutans')
            ->get();

        if ($records->isEmpty()) {
            $this->info('Tidak ada data yang perlu dipindah.');
            return self::SUCCESS;
        }

        $moved = 0;

        foreach ($records as $record) {
            DB::transaction(function () use ($record, &$moved) {
                EdukasiLanjutan::create([
                    'tanggal'             => $record->tanggal,
                    'no_mr'               => $record->no_mr ?: $record->no_reg,
                    'no_reg'              => $record->no_reg,
                    'nama_pasien'         => $record->nama_pasien,
                    'jaminan'             => $record->jaminan,
                    'bulan'               => now()->translatedFormat('F'),
                    'edukasi_kamar'       => $record->edukasi_kamar,
                    'note'                => $record->note,
                    'petugas'             => $record->petugas,
                    'keluarga_pasien'     => $record->keluarga_pasien,
                    'ttd_keluarga_pasien' => $record->ttd_keluarga_pasien,
                    'status'              => 'Menunggu',
                    'quality_control_id'  => $record->id,
                ]);

                // record asli TETAP ADA di quality_controls (riwayat terjaga),
                // hanya statusnya berubah supaya hilang dari menu "Edukasi"
                $record->update(['status' => 'Edukasi lanjutan']);

                $moved++;
            });
        }

        // Kalimat ini sengaja mengandung "pasien dipindahkan" — route
        // POST /quality-control/process-edukasi-lanjutan di api.php
        // mem-parsing angka dari output ini lewat regex.
        $this->info("{$moved} pasien dipindahkan ke Edukasi Lanjutan.");

        return self::SUCCESS;
    }
}