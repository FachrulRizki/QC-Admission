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

    /**
     * GET /api/auth/token
     * Kembalikan Keycloak access token milik user yang sedang login.
     * Digunakan untuk integrasi antar sistem (misal: API Bed IGD).
     */
    Route::get('/auth/token', function () {
        $token        = session('keycloak_access_token');
        $authUser     = session('auth_user');

        if (! $token) {
            return response()->json(['message' => 'Token tidak ditemukan. Silakan login ulang.'], 401);
        }

        // Decode payload JWT untuk info exp dan resource_access (tanpa verify signature)
        $payload = [];
        $parts   = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? [];
        }

        $exp = $payload['exp'] ?? null;

        return response()->json([
            'access_token'    => $token,
            'token_type'      => 'Bearer',
            'expires_at'      => $exp ? date('Y-m-d H:i:s', $exp) : null,
            'expires_in'      => $exp ? max(0, $exp - time()) : null,
            'user' => [
                'id'              => $authUser['id']       ?? null,
                'name'            => $authUser['name']     ?? null,
                'username'        => $authUser['username'] ?? null,
                'roles'           => $authUser['roles']    ?? [],
            ],
            // Isi lengkap roles per client — berguna untuk integrasi (mis. tim Bed IGD)
            'resource_access' => $payload['resource_access'] ?? [],
            'realm_access'    => $payload['realm_access']    ?? [],
        ]);
    });

    /**
     * POST /api/auth/refresh-token
     * Auto-refresh access token menggunakan refresh_token dari session.
     * Dipanggil ketika access_token sudah expired (dapat 401 dari API tujuan).
     */
    Route::post('/auth/refresh-token', function () {
        $refreshToken = session('keycloak_refresh_token');

        if (! $refreshToken) {
            return response()->json([
                'message' => 'Refresh token tidak ditemukan. Silakan login ulang.',
                'action'  => 'relogin',
            ], 401);
        }

        $baseUrl = config('services.keycloak.base_url');
        $realm   = config('services.keycloak.realm');

        $response = \Illuminate\Support\Facades\Http::timeout(10)
            ->asForm()
            ->post("{$baseUrl}/realms/{$realm}/protocol/openid-connect/token", [
                'grant_type'    => 'refresh_token',
                'client_id'     => config('services.keycloak.client_id'),
                'client_secret' => config('services.keycloak.client_secret'),
                'refresh_token' => $refreshToken,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Sesi habis. Silakan login ulang.',
                'action'  => 'relogin',
            ], 401);
        }

        $body            = $response->json();
        $newAccessToken  = $body['access_token']  ?? null;
        $newRefreshToken = $body['refresh_token']  ?? $refreshToken;

        // Simpan token baru ke session
        session([
            'keycloak_access_token'  => $newAccessToken,
            'keycloak_refresh_token' => $newRefreshToken,
        ]);

        // Decode payload
        $payload = [];
        $parts   = explode('.', $newAccessToken ?? '');
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? [];
        }

        $exp      = $payload['exp'] ?? null;
        $authUser = session('auth_user');

        return response()->json([
            'access_token'    => $newAccessToken,
            'token_type'      => 'Bearer',
            'expires_at'      => $exp ? date('Y-m-d H:i:s', $exp) : null,
            'expires_in'      => $body['expires_in'] ?? null,
            'user' => [
                'id'       => $authUser['id']       ?? null,
                'name'     => $authUser['name']     ?? null,
                'username' => $authUser['username'] ?? null,
                'roles'    => $authUser['roles']    ?? [],
            ],
            'resource_access' => $payload['resource_access'] ?? [],
        ]);
    });

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
});
