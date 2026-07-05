<?php

namespace App\Console\Commands;

use App\Models\EdukasiLanjutan;
use App\Models\QualityControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessEdukasiLanjutan extends Command
{
    protected $signature   = 'qc:process-edukasi-lanjutan';
    protected $description = 'Pindahkan data QC >= 2 jam ke edukasi_lanjutans';

    public function handle(): int
    {
        $records = QualityControl::query()
            ->where('status', 'Edukasi')
            ->where('created_at', '<=', now()->subHours(2))
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

                $record->update(['status' => 'Edukasi lanjutan']);
                $moved++;
            });
        }

        // Format output mengandung "pasien dipindahkan" — di-parse oleh route HTTP trigger.
        $this->info("{$moved} pasien dipindahkan ke Edukasi Lanjutan.");

        return self::SUCCESS;
    }
}
