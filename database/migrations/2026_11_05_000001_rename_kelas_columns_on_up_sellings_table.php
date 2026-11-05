<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcw_up_sellings', function (Blueprint $table) {
            if (Schema::hasColumn('qcw_up_sellings', 'rekomendasi_kelas')) {
                $table->renameColumn('rekomendasi_kelas', 'kelas_before');
            }
            if (Schema::hasColumn('qcw_up_sellings', 'kelas_diambil')) {
                $table->renameColumn('kelas_diambil', 'kelas_after');
            }
        });

        // Data lama: kelas_before kosong → isi dengan kelas saat input
        DB::table('qcw_up_sellings')->whereNull('kelas_before')->update(['kelas_before' => DB::raw('kelas')]);
    }

    public function down(): void
    {
        Schema::table('qcw_up_sellings', function (Blueprint $table) {
            if (Schema::hasColumn('qcw_up_sellings', 'kelas_before')) {
                $table->renameColumn('kelas_before', 'rekomendasi_kelas');
            }
            if (Schema::hasColumn('qcw_up_sellings', 'kelas_after')) {
                $table->renameColumn('kelas_after', 'kelas_diambil');
            }
        });
    }
};
