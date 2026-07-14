<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcw_edukasi_lanjutans', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal', 30);
            $table->string('no_mr', 20);
            $table->string('no_reg', 20)->nullable();
            $table->string('nama_pasien', 100)->nullable();
            $table->string('jaminan', 50)->nullable();
            $table->string('bulan', 20);
            $table->string('edukasi_kamar', 100)->nullable();
            $table->text('note')->nullable();
            $table->string('petugas', 100);
            $table->string('keluarga_pasien', 100)->nullable();
            $table->text('ttd_keluarga_pasien')->nullable();
            $table->enum('status', ['Menunggu', 'Selesai'])->default('Menunggu');
            $table->string('status_ranap', 30)->nullable();
            $table->timestamp('ranap_at')->nullable();
            $table->unsignedBigInteger('quality_control_id')->nullable();
            $table->foreign('quality_control_id')->references('id')->on('qcw_quality_controls')->nullOnDelete();
            $table->timestamps();

            $table->index('no_mr');
            $table->index('bulan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcw_edukasi_lanjutans');
    }
};
