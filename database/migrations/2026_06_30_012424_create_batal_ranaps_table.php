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
            $table->string('nama_pasien', 100)->nullable();
            $table->string('keterangan_batal', 100);
            $table->enum('status_ok', ['OK', 'Pending', 'Ditolak'])->default('Pending');
            $table->string('ketersediaan_kamar', 100)->nullable();
            $table->string('diagnosa', 255)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('petugas', 100);
            // SSO / bed management reference
            $table->string('bed_id', 50)->nullable();
            $table->string('ruangan', 100)->nullable();
            $table->timestamps();

            $table->index('no_reg');
            $table->index('status_ok');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batal_ranaps');
    }
};
