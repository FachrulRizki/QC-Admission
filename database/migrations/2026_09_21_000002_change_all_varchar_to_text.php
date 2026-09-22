<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // qcw_batal_ranaps
        Schema::table('qcw_batal_ranaps', function (Blueprint $table) {
            $table->text('nama_pasien')->nullable()->change();
            $table->text('keterangan_batal')->nullable()->change();
            $table->text('ketersediaan_kamar')->nullable()->change();
            $table->text('diagnosa')->nullable()->change();
            $table->text('note')->nullable()->change();
            $table->text('petugas')->nullable()->change();
            $table->text('ruangan')->nullable()->change();
            $table->text('jaminan')->nullable()->change();
        });

        // qcw_quality_controls
        Schema::table('qcw_quality_controls', function (Blueprint $table) {
            $table->text('nama_pasien')->nullable()->change();
            $table->text('jaminan')->nullable()->change();
            $table->text('status_ket')->nullable()->change();
            $table->text('edukasi_kamar')->nullable()->change();
            $table->text('note')->nullable()->change();
            $table->text('petugas')->nullable()->change();
            $table->text('keluarga_pasien')->nullable()->change();
        });

        // qcw_edukasi_lanjutans─
        Schema::table('qcw_edukasi_lanjutans', function (Blueprint $table) {
            $table->text('nama_pasien')->nullable()->change();
            $table->text('jaminan')->nullable()->change();
            $table->text('edukasi_kamar')->nullable()->change();
            $table->text('petugas')->nullable()->change();
            $table->text('keluarga_pasien')->nullable()->change();
        });

        // qcw_up_sellings─
        Schema::table('qcw_up_sellings', function (Blueprint $table) {
            $table->text('nama_pasien')->nullable()->change();
            $table->text('jaminan')->nullable()->change();
            $table->text('nama_ruang')->nullable()->change();
            $table->text('nama_bangsal')->nullable()->change();
            $table->text('kelas')->nullable()->change();
            $table->text('rekomendasi_kelas')->nullable()->change();
            $table->text('kelas_diambil')->nullable()->change();
            $table->text('alasan')->nullable()->change();
            $table->text('petugas')->nullable()->change();
            $table->text('note')->nullable()->change();
        });

        // qcw_activity_logs─
        Schema::table('qcw_activity_logs', function (Blueprint $table) {
            $table->text('user_name')->nullable()->change();
            $table->text('user_role')->nullable()->change();
            $table->text('module')->nullable()->change();
            $table->text('action')->nullable()->change();
        });

        // qcw_alasans─
        Schema::table('qcw_alasans', function (Blueprint $table) {
            $table->text('nama_pasien')->nullable()->change();
            $table->text('jaminan')->nullable()->change();
            $table->text('nama_ruang')->nullable()->change();
            $table->text('nama_bangsal')->nullable()->change();
            $table->text('alasan')->nullable()->change();
            $table->text('petugas')->nullable()->change();
        });

        // qcw_master_data─
        // 'category' dan 'item' dipakai dalam unique(['category','item']),
        // SQL Server tidak bisa index/unique pada TEXT, jadi diperpanjang ke 500 saja
        Schema::table('qcw_master_data', function (Blueprint $table) {
            $table->string('item', 500)->change();
        });
    }

    public function down(): void
    {
        // qcw_batal_ranaps
        Schema::table('qcw_batal_ranaps', function (Blueprint $table) {
            $table->string('nama_pasien', 100)->nullable()->change();
            $table->string('keterangan_batal', 100)->nullable()->change();
            $table->string('ketersediaan_kamar', 100)->nullable()->change();
            $table->string('diagnosa', 255)->nullable()->change();
            $table->string('note', 255)->nullable()->change();
            $table->string('petugas', 100)->nullable()->change();
            $table->string('ruangan', 100)->nullable()->change();
            $table->string('jaminan', 50)->nullable()->change();
        });

        // qcw_quality_controls
        Schema::table('qcw_quality_controls', function (Blueprint $table) {
            $table->string('nama_pasien', 100)->nullable()->change();
            $table->string('jaminan', 50)->nullable()->change();
            $table->string('status_ket', 100)->nullable()->change();
            $table->string('edukasi_kamar', 100)->nullable()->change();
            $table->string('note', 255)->nullable()->change();
            $table->string('petugas', 100)->nullable()->change();
            $table->string('keluarga_pasien', 100)->nullable()->change();
        });

        // qcw_edukasi_lanjutans─
        Schema::table('qcw_edukasi_lanjutans', function (Blueprint $table) {
            $table->string('nama_pasien', 100)->nullable()->change();
            $table->string('jaminan', 50)->nullable()->change();
            $table->string('edukasi_kamar', 100)->nullable()->change();
            $table->string('petugas', 100)->nullable()->change();
            $table->string('keluarga_pasien', 100)->nullable()->change();
        });

        // qcw_up_sellings─
        Schema::table('qcw_up_sellings', function (Blueprint $table) {
            $table->string('nama_pasien', 100)->nullable()->change();
            $table->string('jaminan', 50)->nullable()->change();
            $table->string('nama_ruang', 100)->nullable()->change();
            $table->string('nama_bangsal', 100)->nullable()->change();
            $table->string('kelas', 50)->nullable()->change();
            $table->string('rekomendasi_kelas', 50)->nullable()->change();
            $table->string('kelas_diambil', 50)->nullable()->change();
            $table->string('alasan', 255)->nullable()->change();
            $table->string('petugas', 100)->nullable()->change();
            $table->string('note', 255)->nullable()->change();
        });

        // qcw_activity_logs─
        Schema::table('qcw_activity_logs', function (Blueprint $table) {
            $table->string('user_name', 100)->nullable()->change();
            $table->string('user_role', 30)->nullable()->change();
            $table->string('module', 50)->nullable()->change();
            $table->string('action', 30)->nullable()->change();
        });

        // qcw_alasans─
        Schema::table('qcw_alasans', function (Blueprint $table) {
            $table->string('nama_pasien', 100)->nullable()->change();
            $table->string('jaminan', 50)->nullable()->change();
            $table->string('nama_ruang', 100)->nullable()->change();
            $table->string('nama_bangsal', 100)->nullable()->change();
            $table->string('alasan', 100)->nullable()->change();
            $table->string('petugas', 100)->nullable()->change();
        });

        // qcw_master_data─
        Schema::table('qcw_master_data', function (Blueprint $table) {
            $table->string('item', 150)->change();
        });
    }
};
