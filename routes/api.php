<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Http\Controllers\QcAdmission\DashboardController;
use App\Http\Controllers\QcAdmission\QualityControlController;
use App\Http\Controllers\QcAdmission\BatalRanapController;
use App\Http\Controllers\QcAdmission\EdukasiLanjutanController;
use App\Http\Controllers\QcAdmission\UpSellingController;
use App\Http\Controllers\QcAdmission\PegawaiController;
use App\Http\Controllers\QcAdmission\PasienController;
use App\Http\Controllers\QcAdmission\MasterDataController;
use App\Http\Controllers\QcAdmission\ActivityLogController;

Route::middleware(['keycloak.auth'])->group(function () {

    // Read-only — semua role yang sudah terautentikasi 
    Route::get('/master-data', [MasterDataController::class, 'index']);
    Route::get('/pegawai',     [PegawaiController::class,    'index']);
    Route::get('/pasien',      [PasienController::class,     'index']);

    // Batal Ranap view — kasir, qc_admission, admin
    Route::get('batal-ranap',      [BatalRanapController::class, 'index']);
    Route::get('batal-ranap/{id}', [BatalRanapController::class, 'show']);

    // Bed management read — kasir boleh lihat
    Route::get('bed-management/beds', function (Request $request) {
        $bedIgd = app(\App\Services\QcAdmission\BedIgdService::class);
        $result = $bedIgd->getBedsByNoReg(trim($request->query('no_reg', '')));
        return response()->json(['beds' => $result['beds'], 'source' => $result['source']]);
    });

    // Butuh permission dashboard:view 
    Route::middleware(['keycloak.role:dashboard:view'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });

    // Butuh permission quality-control:view
    Route::middleware(['keycloak.role:quality-control:view'])->group(function () {
        Route::get('quality-control',         [QualityControlController::class, 'index']);
        Route::get('quality-control/{id}',    [QualityControlController::class, 'show']);
    });

    // Butuh permission quality-control:write ─
    Route::middleware(['keycloak.role:quality-control:write'])->group(function () {
        Route::post('quality-control/process-edukasi-lanjutan', function () {
            try {
                Artisan::call('qc:process-edukasi-lanjutan');
                $output = trim(Artisan::output());
                $count  = preg_match('/(\d+)\s+pasien\s+dipindahkan/', $output, $m) ? (int) $m[1] : 0;
                return response()->json(['success' => true, 'output' => $output, 'count' => $count]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'count' => 0], 500);
            }
        });
        Route::post  ('quality-control',        [QualityControlController::class, 'store']);
        Route::put   ('quality-control/{id}',   [QualityControlController::class, 'update']);
        Route::patch ('quality-control/{id}/ranap', [QualityControlController::class, 'updateRanap']);
    });

    // Butuh permission quality-control:delete 
    Route::middleware(['keycloak.role:quality-control:delete'])->group(function () {
        Route::delete('quality-control/{id}', [QualityControlController::class, 'destroy']);
    });

    // Butuh permission edukasi-lanjutan:view 
    Route::middleware(['keycloak.role:edukasi-lanjutan:view'])->group(function () {
        Route::get('edukasi-lanjutan/sync-rsus', [EdukasiLanjutanController::class, 'syncRsus']);
        Route::get('edukasi-lanjutan-pending',   [EdukasiLanjutanController::class, 'pending']);
        Route::get('edukasi-lanjutan',           [EdukasiLanjutanController::class, 'index']);
        Route::get('edukasi-lanjutan/{id}',      [EdukasiLanjutanController::class, 'show']);
    });

    // Butuh permission edukasi-lanjutan:write 
    Route::middleware(['keycloak.role:edukasi-lanjutan:write'])->group(function () {
        Route::post ('edukasi-lanjutan',            [EdukasiLanjutanController::class, 'store']);
        Route::put  ('edukasi-lanjutan/{id}',       [EdukasiLanjutanController::class, 'update']);
        Route::patch('edukasi-lanjutan/{id}/ranap', [EdukasiLanjutanController::class, 'updateRanap']);
    });

    // Butuh permission edukasi-lanjutan:delete 
    Route::middleware(['keycloak.role:edukasi-lanjutan:delete'])->group(function () {
        Route::delete('edukasi-lanjutan/{id}', [EdukasiLanjutanController::class, 'destroy']);
    });

    // Butuh permission up-selling:view 
    Route::middleware(['keycloak.role:up-selling:view'])->group(function () {
        Route::get('up-selling',      [UpSellingController::class, 'index']);
        Route::get('up-selling/{id}', [UpSellingController::class, 'show']);
    });

    // Butuh permission up-selling:write 
    Route::middleware(['keycloak.role:up-selling:write'])->group(function () {
        Route::post('up-selling',       [UpSellingController::class, 'store']);
        Route::put ('up-selling/{id}',  [UpSellingController::class, 'update']);
    });

    // Butuh permission up-selling:delete─
    Route::middleware(['keycloak.role:up-selling:delete'])->group(function () {
        Route::delete('up-selling/{id}', [UpSellingController::class, 'destroy']);
    });

    // Butuh permission batal-ranap:write─
    Route::middleware(['keycloak.role:batal-ranap:write'])->group(function () {
        Route::post('batal-ranap',                [BatalRanapController::class, 'store']);
        Route::put ('batal-ranap/{id}',           [BatalRanapController::class, 'update']);
        Route::patch('batal-ranap/{id}/verifikasi', [BatalRanapController::class, 'verifikasi']);
    });

    // Butuh permission batal-ranap:delete
    Route::middleware(['keycloak.role:batal-ranap:delete'])->group(function () {
        Route::delete('batal-ranap/{id}', [BatalRanapController::class, 'destroy']);
    });

    // Butuh permission batal-ranap:closing─
    Route::middleware(['keycloak.role:batal-ranap:closing'])->group(function () {
        Route::patch('batal-ranap/{id}/konfirmasi-closing', [BatalRanapController::class, 'konfirmasiClosing']);
        Route::get  ('batal-ranap/{id}/bed-history',        [BatalRanapController::class, 'bedHistory']);
    });

    // Butuh permission bed-management:write
    Route::middleware(['keycloak.role:bed-management:write'])->group(function () {
        Route::post('bed-management/update-status', function (Request $request) {
            $v      = $request->validate(['no_reg' => 'required|string', 'kode_bed' => 'required|string']);
            $bedIgd = app(\App\Services\QcAdmission\BedIgdService::class);
            $result = $bedIgd->releaseBed($v['kode_bed'], $v['no_reg']);
            return response()->json($result, $result['success'] ? 200 : 500);
        });
    });

    // Butuh permission master-data:write─
    Route::middleware(['keycloak.role:master-data:write'])->group(function () {
        Route::post('/master-data',              [MasterDataController::class, 'store']);
        Route::put ('/master-data/{category}',   [MasterDataController::class, 'update']);
    });

    // Butuh permission master-data:delete
    Route::middleware(['keycloak.role:master-data:delete'])->group(function () {
        Route::delete('/master-data/{category}/{index}', [MasterDataController::class, 'destroy']);
    });

    // Butuh permission activity-log:view─
    Route::middleware(['keycloak.role:activity-log:view'])->group(function () {
        Route::get('/activity-log', [ActivityLogController::class, 'index']);
    });

    // Butuh permission user-management:view
    Route::middleware(['keycloak.role:user-management:view'])->group(function () {
        Route::get('/users', function () {
            try {
                $keycloak = app(\App\Services\KeycloakService::class);
                $users    = $keycloak->getUsers();
                return response()->json(['data' => $users, 'source' => 'keycloak', 'total' => count($users)]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Keycloak Admin API gagal saat fetch users.', [
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'data'    => [],
                    'source'  => 'error',
                    'total'   => 0,
                    'message' => 'Tidak dapat memuat data user dari Keycloak. Pastikan service account memiliki role view-users.',
                ], 503);
            }
        });

        Route::post('/users/refresh-cache', function () {
            $keycloak = app(\App\Services\KeycloakService::class);
            $keycloak->flushUsersCache();
            return response()->json(['message' => 'Cache user berhasil direset. Data akan diambil ulang dari Keycloak.']);
        });
    });
});
