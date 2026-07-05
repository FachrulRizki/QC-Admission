<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batal_ranaps', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal', 30);
            $table->string('jam_input', 20);
            $table->string('no_reg', 20);
            $table->string('no_mr', 20)->nullable();
            $table->string('tgl_daftar', 30)->nullable();
            $table->string('jam_daftar', 20)->nullable();
            $table->string('nama_pasien', 100)->nullable();
            $table->string('keterangan_batal', 100);
            $table->string('status_ok', 30)->nullable();
            $table->string('status_closing', 30)->nullable();
            $table->string('ketersediaan_kamar', 100)->nullable();
            $table->string('diagnosa', 255)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('petugas', 100);
            $table->string('bed_id', 50)->nullable();
            $table->string('ruangan', 100)->nullable();
            $table->timestamps();

            $table->index('no_reg');
            $table->index('status_ok');
            $table->index('status_closing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batal_ranaps');
    }
};
