<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QcAdmission\AuthController;
use App\Http\Controllers\QcAdmission\DashboardController;
use App\Http\Controllers\QcAdmission\QualityControlController;
use App\Http\Controllers\QcAdmission\BatalRanapController;
use App\Http\Controllers\QcAdmission\EdukasiLanjutanController;
use App\Http\Controllers\QcAdmission\UpSellingController;

/*
|--------------------------------------------------------------------------
| QC Admission API Routes
|--------------------------------------------------------------------------
*/

// ── Auth (public) ─────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// ── Protected routes (Sanctum) ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Quality Control
    Route::apiResource('quality-control', QualityControlController::class);

    // Batal Ranap
    Route::apiResource('batal-ranap', BatalRanapController::class);
    Route::patch('batal-ranap/{id}/verifikasi', [BatalRanapController::class, 'verifikasi']);

    // Edukasi Lanjutan
    Route::apiResource('edukasi-lanjutan', EdukasiLanjutanController::class);

    // Up Selling
    Route::apiResource('up-selling', UpSellingController::class);
});
