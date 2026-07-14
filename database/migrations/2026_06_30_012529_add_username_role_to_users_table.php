<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom aplikasi ke tabel qcw_users:
 * username, role (dengan enum final), sso_id, login_type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcw_users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->nullable()->after('name');
            $table->enum('role', ['admin', 'qc_admission', 'kasir'])->default('qc_admission')->after('email');
            $table->string('sso_id', 100)->nullable()->after('role');
            $table->string('login_type', 10)->default('local')->after('sso_id');
        });
    }

    public function down(): void
    {
        Schema::table('qcw_users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'sso_id', 'login_type']);
        });
    }
};
