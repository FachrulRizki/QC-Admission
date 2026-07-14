<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcw_master_data', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index()
                  ->comment('Nama kategori, e.g. ruangan, kelas, jaminan');
            $table->string('item', 150)
                  ->comment('Nilai item dalam kategori');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcw_master_data');
    }
};
