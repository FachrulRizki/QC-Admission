<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('up_sellings', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal', 30);
            $table->string('jam_input', 20);
            $table->string('no_reg', 20);
            $table->string('nama_pasien', 100)->nullable();
            $table->string('jaminan', 50)->nullable();
            $table->string('rekomendasi_kelas', 50)->nullable();
            $table->string('kelas_diambil', 50)->nullable();
            $table->string('alasan', 255)->nullable();
            $table->string('petugas', 100);
            $table->enum('status', ['Berhasil', 'Tidak Berhasil', 'Pending'])->default('Pending');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('no_reg');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('up_sellings');
    }
};
