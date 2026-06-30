<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 100)->nullable();
            $table->string('user_role', 30)->nullable();
            $table->string('module', 50);        // quality-control, batal-ranap, dll
            $table->string('action', 30);        // create, update, delete, login, logout
            $table->string('subject', 200)->nullable(); // deskripsi singkat
            $table->json('payload')->nullable();  // data sebelum/sesudah
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('module');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
