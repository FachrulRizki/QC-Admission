<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog;
use App\Http\Controllers\QcAdmission\AuthController;
use App\Http\Controllers\QcAdmission\DashboardController;
use App\Http\Controllers\QcAdmission\QualityControlController;
use App\Http\Controllers\QcAdmission\BatalRanapController;
use App\Http\Controllers\QcAdmission\EdukasiLanjutanController;
use App\Http\Controllers\QcAdmission\UpSellingController;
use App\Http\Controllers\QcAdmission\PegawaiController;
use App\Http\Controllers\QcAdmission\PasienController;
use App\Http\Controllers\QcAdmission\MasterDataController;
use App\Http\Controllers\QcAdmission\ActivityLogController;

// Public

Route::prefix('auth')->group(function () {
    Route::post('/login',        [AuthController::class, 'login']);
    Route::post('/sso/callback', [AuthController::class, 'ssoCallback']);
});

Route::get('/config', fn() => response()->json([
    'sso_enabled'        => (bool) config('services.sso_enabled', false),
    'rsus_db_enabled'    => (bool) config('services.rsus_db_enabled', false),
    'keycloak_base_url'  => config('services.keycloak.base_url', ''),
    'keycloak_realm'     => config('services.keycloak.realm', 'master'),
    'keycloak_client_id' => config('services.keycloak.client_id', 'qc-admission'),
]));

// Protected

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Referensi — semua role
    Route::get('/master-data', [MasterDataController::class, 'index']);
    Route::get('/pegawai',     [PegawaiController::class,    'index']);
    Route::get('/pasien',      [PasienController::class,     'index']);

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::post  ('/master-data',                    [MasterDataController::class, 'store']);
        Route::put   ('/master-data/{category}',         [MasterDataController::class, 'update']);
        Route::delete('/master-data/{category}/{index}', [MasterDataController::class, 'destroy']);
        Route::get   ('/activity-log',                   [ActivityLogController::class, 'index']);

        Route::get('/users', fn() =>
            response()->json(User::select('id','name','username','email','role','login_type','created_at')->get())
        );
        Route::post('/users', function (Request $request) {
            $data = $request->validate([
                'name'     => 'required|string|max:100',
                'username' => 'required|string|unique:users|max:50',
                'email'    => 'required|email|unique:users',
                'password' => 'required|string|min:6',
                'role'     => 'required|in:admin,qc_admission,kasir',
            ]);
            $user = User::create([...$data, 'password' => Hash::make($data['password']), 'login_type' => 'local']);
            ActivityLog::record('user', 'create', "User baru: {$user->name} ({$user->role})");
            return response()->json(['user' => $user], 201);
        });
        Route::put('/users/{id}', function (Request $request, $id) {
            $user = User::findOrFail($id);
            $data = $request->validate([
                'name'     => 'sometimes|string|max:100',
                'username' => "sometimes|string|unique:users,username,{$id}|max:50",
                'email'    => "sometimes|email|unique:users,email,{$id}",
                'password' => 'sometimes|string|min:6',
                'role'     => 'sometimes|in:admin,qc_admission,kasir',
            ]);
            if (isset($data['password'])) $data['password'] = Hash::make($data['password']);
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

    // Admin + QC Admission
    Route::middleware('role:admin,qc_admission')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Quality Control — spesifik route sebelum apiResource
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

        // Edukasi Lanjutan — spesifik route sebelum apiResource
        Route::get('edukasi-lanjutan/sync-rsus', [EdukasiLanjutanController::class, 'syncRsus']);
        Route::get('edukasi-lanjutan-pending',   [EdukasiLanjutanController::class, 'pending']);
        Route::apiResource('edukasi-lanjutan', EdukasiLanjutanController::class);
        Route::patch('edukasi-lanjutan/{id}/ranap', [EdukasiLanjutanController::class, 'updateRanap']);

        Route::apiResource('up-selling', UpSellingController::class);
    });

    // Batal Ranap — GET semua role, mutasi hanya admin + qc_admission
    Route::get('batal-ranap',      [BatalRanapController::class, 'index']);
    Route::get('batal-ranap/{id}', [BatalRanapController::class, 'show']);
    Route::middleware('role:admin,qc_admission')->group(function () {
        Route::post  ('batal-ranap',                            [BatalRanapController::class, 'store']);
        Route::put   ('batal-ranap/{id}',                       [BatalRanapController::class, 'update']);
        Route::delete('batal-ranap/{id}',                       [BatalRanapController::class, 'destroy']);
        Route::patch ('batal-ranap/{id}/verifikasi',            [BatalRanapController::class, 'verifikasi']);
        Route::patch ('batal-ranap/{id}/konfirmasi-closing',    [BatalRanapController::class, 'konfirmasiClosing']);
        Route::get   ('batal-ranap/{id}/bed-history',           [BatalRanapController::class, 'bedHistory']);
    });

    // Bed Management IGD proxy
    Route::prefix('bed-management')->group(function () {
        Route::get('/beds', function (Request $request) {
            $noReg  = trim($request->query('no_reg', ''));
            $bedIgd = app(\App\Services\QcAdmission\BedIgdService::class);
            $result = $bedIgd->getBedsByNoReg($noReg);
            return response()->json(['beds' => $result['beds'], 'source' => $result['source']]);
        });

        Route::post('/update-status', function (Request $request) {
            $v = $request->validate([
                'no_reg'   => 'required|string',
                'kode_bed' => 'required|string',
            ]);
            /** @var \App\Services\QcAdmission\BedIgdService $bedIgd */
            $bedIgd = app(\App\Services\QcAdmission\BedIgdService::class);
            $result = $bedIgd->releaseBed($v['kode_bed'], $v['no_reg']);
            return response()->json($result, $result['success'] ? 200 : 500);
        });
    });
});
