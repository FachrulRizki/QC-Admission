<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog;
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

    // ── Read-only — semua role yang sudah terautentikasi ─────────────────────
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

    // ── Admin + QC Admission ──────────────────────────────────────────────────
    Route::middleware(['keycloak.role:admin,qc_admission'])->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Quality Control
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
        Route::apiResource('quality-control', QualityControlController::class);
        Route::patch('quality-control/{id}/ranap', [QualityControlController::class, 'updateRanap']);

        // Edukasi Lanjutan
        Route::get('edukasi-lanjutan/sync-rsus', [EdukasiLanjutanController::class, 'syncRsus']);
        Route::get('edukasi-lanjutan-pending',    [EdukasiLanjutanController::class, 'pending']);
        Route::apiResource('edukasi-lanjutan', EdukasiLanjutanController::class);
        Route::patch('edukasi-lanjutan/{id}/ranap', [EdukasiLanjutanController::class, 'updateRanap']);

        // Up Selling
        Route::apiResource('up-selling', UpSellingController::class);

        // Batal Ranap write
        Route::post  ('batal-ranap',                         [BatalRanapController::class, 'store']);
        Route::put   ('batal-ranap/{id}',                    [BatalRanapController::class, 'update']);
        Route::delete('batal-ranap/{id}',                    [BatalRanapController::class, 'destroy']);
        Route::patch ('batal-ranap/{id}/verifikasi',         [BatalRanapController::class, 'verifikasi']);
        Route::patch ('batal-ranap/{id}/konfirmasi-closing', [BatalRanapController::class, 'konfirmasiClosing']);
        Route::get   ('batal-ranap/{id}/bed-history',        [BatalRanapController::class, 'bedHistory']);

        // Bed management write
        Route::post('bed-management/update-status', function (Request $request) {
            $v      = $request->validate(['no_reg' => 'required|string', 'kode_bed' => 'required|string']);
            $bedIgd = app(\App\Services\QcAdmission\BedIgdService::class);
            $result = $bedIgd->releaseBed($v['kode_bed'], $v['no_reg']);
            return response()->json($result, $result['success'] ? 200 : 500);
        });
    });

    // ── Admin only ────────────────────────────────────────────────────────────
    Route::middleware(['keycloak.role:admin'])->group(function () {

        // Master Data write
        Route::post  ('/master-data',                    [MasterDataController::class, 'store']);
        Route::put   ('/master-data/{category}',         [MasterDataController::class, 'update']);
        Route::delete('/master-data/{category}/{index}', [MasterDataController::class, 'destroy']);

        // Activity Log
        Route::get('/activity-log', [ActivityLogController::class, 'index']);

        // User Management — inline (admin langsung kelola user lokal / SSO)
        Route::get('/users', function () {
            return response()->json(
                User::select('id', 'name', 'username', 'email', 'role', 'login_type', 'created_at')->get()
            );
        });

        Route::post('/users', function (Request $request) {
            // User yang dibuat dari sini adalah akun service / fallback
            // Untuk produksi, user sebaiknya dikelola di Keycloak
            $data = $request->validate([
                'name'     => 'required|string|max:100',
                'username' => 'required|string|unique:users|max:50',
                'email'    => 'required|email|unique:users',
                'role'     => 'required|string|max:50',
            ]);
            $user = User::create([...$data, 'login_type' => 'sso']);
            ActivityLog::record('user', 'create', "User baru: {$user->name} ({$user->role})");
            return response()->json(['user' => $user], 201);
        });

        Route::put('/users/{id}', function (Request $request, $id) {
            $user = User::findOrFail($id);
            $data = $request->validate([
                'name'  => 'sometimes|string|max:100',
                'email' => "sometimes|email|unique:users,email,{$id}",
                'role'  => 'sometimes|string|max:50',
            ]);
            $user->update($data);
            ActivityLog::record('user', 'update', "User diupdate: {$user->name} ({$user->role})");
            return response()->json(['user' => $user]);
        });

        Route::delete('/users/{id}', function ($id) {
            $user = User::findOrFail($id);
            ActivityLog::record('user', 'delete', "User dihapus: {$user->name}");
            $user->delete();
            return response()->json(['message' => 'User dihapus.']);
        });
    });
});
