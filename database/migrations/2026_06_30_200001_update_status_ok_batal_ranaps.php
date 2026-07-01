<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand ENUM status_ok batal_ranaps:
     * Dari: ['OK','Pending','Ditolak']
     * Ke  : ['OK','Pending','Ditolak','Bedah','Non Bedah']
     *
     * 'Bedah' dan 'Non Bedah' adalah jenis status dari form input
     * (sesuai field Status OK di BatalRanapFormDialog).
     */
    public function up(): void
    {
        // Lepas ENUM → string bebas dulu
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->string('status_ok', 20)->nullable()->default(null)->change();
        });

        // Pasang ENUM baru: hanya Bedah dan Non Bedah (sesuai requirement)
        // Status awal null (belum diverifikasi)
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->enum('status_ok', ['Bedah', 'Non Bedah'])
                  ->nullable()
                  ->default(null)
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->string('status_ok', 20)->default('Pending')->change();
        });

        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->enum('status_ok', ['OK','Pending','Ditolak'])
                  ->default('Pending')
                  ->change();
        });
    }
};
