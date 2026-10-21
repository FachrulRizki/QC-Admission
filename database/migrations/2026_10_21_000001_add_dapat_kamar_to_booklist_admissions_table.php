<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcw_booklist_admissions', function (Blueprint $table) {
            $table->timestamp('waktu_dapat_kamar')->nullable();
            $table->string('nama_bangsal', 150)->nullable();
            $table->string('nama_ruang', 150)->nullable();

            $table->index('waktu_dapat_kamar');
        });
    }

    public function down(): void
    {
        Schema::table('qcw_booklist_admissions', function (Blueprint $table) {
            $table->dropIndex(['waktu_dapat_kamar']);
            $table->dropColumn(['waktu_dapat_kamar', 'nama_bangsal', 'nama_ruang']);
        });
    }
};
