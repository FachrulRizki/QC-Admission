<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcw_alasans', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal', 30)->nullable();
            $table->string('jam_input', 10)->nullable();
            $table->string('no_reg', 20);
            $table->string('no_mr', 20)->nullable();
            $table->string('nama_pasien', 100)->nullable();
            $table->string('jaminan', 50)->nullable();
            $table->string('tgl_daftar', 20)->nullable();
            $table->string('jam_daftar', 10)->nullable();
            $table->string('nama_ruang', 100)->nullable();
            $table->string('nama_bangsal', 100)->nullable();
            $table->string('alasan', 100)->nullable(); 
            $table->text('catatan')->nullable();
            $table->string('petugas', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcw_alasans');
    }
};
