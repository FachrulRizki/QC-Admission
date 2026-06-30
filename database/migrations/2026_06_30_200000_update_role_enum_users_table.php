<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah ENUM role dari ['admin','petugas','supervisor']
     * ke ['admin','qc_admission','kasir'].
     *
     * Strategi:
     *  1. Ubah kolom ke string dulu (lepas constraint ENUM)
     *  2. Update data lama
     *  3. Ubah ke ENUM baru
     */
    public function up(): void
    {
        // Lepas ENUM constraint → string bebas
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('qc_admission')->change();
        });

        // Migrate nilai lama ke nilai baru
        DB::table('users')->whereIn('role', ['petugas', 'supervisor'])->update(['role' => 'qc_admission']);

        // Pasang ENUM baru
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'qc_admission', 'kasir'])->default('qc_admission')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('petugas')->change();
        });

        DB::table('users')->where('role', 'qc_admission')->update(['role' => 'petugas']);
        DB::table('users')->where('role', 'kasir')->update(['role' => 'petugas']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'petugas', 'supervisor'])->default('petugas')->change();
        });
    }
};
