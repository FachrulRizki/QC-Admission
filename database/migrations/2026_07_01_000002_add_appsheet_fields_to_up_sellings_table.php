<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('up_sellings', function (Blueprint $table) {
            $table->string('no_mr', 20)->nullable()->after('no_reg');
            $table->string('tgl_daftar', 30)->nullable()->after('no_mr');
            $table->string('nama_ruang', 100)->nullable()->after('jaminan');
            $table->string('nama_bangsal', 100)->nullable()->after('nama_ruang');
            $table->string('kelas', 50)->nullable()->after('nama_bangsal');
        });
    }

    public function down(): void
    {
        Schema::table('up_sellings', function (Blueprint $table) {
            $table->dropColumn(['no_mr', 'tgl_daftar', 'nama_ruang', 'nama_bangsal', 'kelas']);
        });
    }
};
