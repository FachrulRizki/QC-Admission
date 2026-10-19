<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcw_booklist_admissions', function (Blueprint $table) {
            // Status tindak lanjut (pasien sudah dihubungi petugas)
            $table->boolean('sudah_dihubungi')->default(false)->after('petugas');
            $table->timestamp('waktu_dihubungi')->nullable()->after('sudah_dihubungi');
            $table->text('catatan_dihubungi')->nullable()->after('waktu_dihubungi');

            $table->index('sudah_dihubungi');
        });
    }

    public function down(): void
    {
        Schema::table('qcw_booklist_admissions', function (Blueprint $table) {
            $table->dropIndex(['sudah_dihubungi']);
            $table->dropColumn(['sudah_dihubungi', 'waktu_dihubungi', 'catatan_dihubungi']);
        });
    }
};
