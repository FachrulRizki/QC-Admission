<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // QC — tandai jika pasien sudah pindah rawat inap
        Schema::table('quality_controls', function (Blueprint $table) {
            $table->string('status_ranap', 30)->nullable()->after('status')
                  ->comment('null = belum ranap, "Pindah Ranap" = sudah pindah ke rawat inap');
            $table->timestamp('ranap_at')->nullable()->after('status_ranap')
                  ->comment('Waktu status berubah ke Pindah Ranap');
        });

        // Edukasi Lanjutan — idem
        Schema::table('edukasi_lanjutans', function (Blueprint $table) {
            $table->string('status_ranap', 30)->nullable()->after('status')
                  ->comment('null = belum ranap, "Pindah Ranap" = sudah pindah ke rawat inap');
            $table->timestamp('ranap_at')->nullable()->after('status_ranap');
        });
    }

    public function down(): void
    {
        Schema::table('quality_controls', function (Blueprint $table) {
            $table->dropColumn(['status_ranap', 'ranap_at']);
        });
        Schema::table('edukasi_lanjutans', function (Blueprint $table) {
            $table->dropColumn(['status_ranap', 'ranap_at']);
        });
    }
};
