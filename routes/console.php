<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\ProcessEdukasiLanjutan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cek setiap menit — pasien QC yang sudah >= 2 jam sejak entry
// otomatis dipindahkan ke Edukasi Lanjutan
Schedule::command('qc:process-edukasi-lanjutan')->everyMinute();
Schedule::command(ProcessEdukasiLanjutan::class)->everyFiveMinutes();

