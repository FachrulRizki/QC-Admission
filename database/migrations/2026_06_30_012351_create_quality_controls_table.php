<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcw_quality_controls', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal', 30);
            $table->string('jam_input', 20);
            $table->string('tgl_daftar', 30)->nullable();
            $table->string('jam_daftar', 20)->nullable();
            $table->string('no_mr', 20)->nullable();
            $table->string('no_reg', 20);
            $table->string('nama_pasien', 100)->nullable();
            $table->string('jaminan', 50)->nullable();
            $table->string('status_ket', 100)->nullable();
            $table->string('edukasi_kamar', 100)->nullable();
            $table->string('durasi_tunggu', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('petugas', 100);
            $table->enum('status', ['Edukasi', 'Edukasi lanjutan', 'Masuk'])->default('Edukasi');
            $table->string('status_ranap', 30)->nullable();
            $table->timestamp('ranap_at')->nullable();
            $table->string('keluarga_pasien', 100)->nullable();
            $table->text('ttd_keluarga_pasien')->nullable();
            $table->timestamps();

            $table->index(['no_mr', 'no_reg']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcw_quality_controls');
    }
};
