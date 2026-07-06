<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->string('jaminan', 50)->nullable()->after('nama_pasien');
        });
    }

    public function down(): void
    {
        Schema::table('batal_ranaps', function (Blueprint $table) {
            $table->dropColumn('jaminan');
        });
    }
};
