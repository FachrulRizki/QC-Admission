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
            $table->string('status_ok', 20)->default('Pending')->change();
        });

        // Pasang ENUM baru yang lengkap
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->enum('status_ok', ['OK','Pending','Ditolak','Bedah','Non Bedah'])
                  ->default('Pending')
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
