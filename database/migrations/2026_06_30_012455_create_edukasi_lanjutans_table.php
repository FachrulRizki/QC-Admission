<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edukasi_lanjutans', function (Blueprint $table) {
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
            $table->text('ttd_keluarga_pasien')->nullable(); // base64 signature
            $table->enum('status', ['Menunggu', 'Selesai'])->default('Menunggu');
            // Foreign reference to quality_control
            $table->unsignedBigInteger('quality_control_id')->nullable();
            $table->foreign('quality_control_id')->references('id')->on('quality_controls')->nullOnDelete();
            $table->timestamps();

            $table->index('no_mr');
            $table->index('bulan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edukasi_lanjutans');
    }
};
