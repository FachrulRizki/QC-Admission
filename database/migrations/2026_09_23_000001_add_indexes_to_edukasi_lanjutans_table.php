<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcw_edukasi_lanjutans', function (Blueprint $table) {
            // Index untuk filter tanggal (paling sering dipakai)
            $table->index('created_at', 'idx_edukasi_created_at');
            // Index untuk filter status
            $table->index('status', 'idx_edukasi_status');
            // Composite index untuk query utama: date range + status
            $table->index(['created_at', 'status'], 'idx_edukasi_created_status');
        });
    }

    public function down(): void
    {
        Schema::table('qcw_edukasi_lanjutans', function (Blueprint $table) {
            $table->dropIndex('idx_edukasi_created_at');
            $table->dropIndex('idx_edukasi_status');
            $table->dropIndex('idx_edukasi_created_status');
        });
    }
};
