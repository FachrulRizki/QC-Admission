<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_controls', function (Blueprint $table) {
            $table->text('edukasi_kamar')->nullable()->change();
        });

        Schema::table('edukasi_lanjutans', function (Blueprint $table) {
            $table->text('edukasi_kamar')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quality_controls', function (Blueprint $table) {
            $table->string('edukasi_kamar', 100)->nullable()->change();
        });

        Schema::table('edukasi_lanjutans', function (Blueprint $table) {
            $table->string('edukasi_kamar', 100)->nullable()->change();
        });
    }
};
