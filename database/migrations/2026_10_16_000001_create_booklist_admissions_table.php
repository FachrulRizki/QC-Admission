<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcw_booklist_admissions', function (Blueprint $table) {
            $table->id();

            // Tanggal & jam pasien masuk booklist
            $table->string('tanggal_booklist', 30);

            // Data pasien (autofill dari RSUS)
            $table->string('no_reg', 30)->nullable();
            $table->string('no_mr', 30)->nullable();
            $table->string('nama_pasien', 150)->nullable();
            $table->string('no_hp', 50)->nullable();
            $table->string('jaminan', 100)->nullable();
            $table->string('kelas', 100)->nullable();
            $table->string('dpjp', 150)->nullable();
            $table->text('diagnosa')->nullable();

            // Form yang diinput petugas
            $table->string('tanggal_op', 30)->nullable();   
            $table->string('status_pasien', 30)->nullable(); 
            $table->text('catatan')->nullable();

            // Meta
            $table->string('petugas', 150)->nullable();
            $table->timestamps();

            $table->index('no_reg');
            $table->index('no_mr');
            $table->index('status_pasien');
            $table->index('tanggal_booklist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcw_booklist_admissions');
    }
};
