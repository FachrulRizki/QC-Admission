<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batal_ranaps', function (Blueprint $table) {
            // Field dari AppSheet yang belum ada
            $table->string('no_mr', 20)->nullable()->after('no_reg');
            $table->string('tgl_daftar', 30)->nullable()->after('no_mr');
            $table->string('jam_daftar', 20)->nullable()->after('tgl_daftar');
            // status_ok: Bedah | Non Bedah | null (belum diverifikasi)
            // Ubah dari enum ke string agar lebih fleksibel
            // status_closing: Siap Closing | Belum Siap Closing
            $table->string('status_closing', 30)->nullable()->after('status_ok');
        });

        // Ubah kolom status_ok agar bisa null dan nilai baru
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->string('status_ok', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->dropColumn(['no_mr', 'tgl_daftar', 'jam_daftar', 'status_closing']);
        });
    }
};
