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
    Route::post('/login', [AuthController::class, 'login']);              // local
    Route::post('/sso/callback', [AuthController::class, 'ssoCallback']); // SSO Keycloak
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
    // Auto-populate from QC (2-hour trigger endpoint)
    Route::get('edukasi-lanjutan/pending', [EdukasiLanjutanController::class, 'pending']);

    // Up Selling
    Route::apiResource('up-selling', UpSellingController::class);

    // Bed Management IGD — proxy ke sistem existing
    Route::prefix('bed-management')->group(function () {
        // Fetch daftar bed (occupied/available) dari sistem Bed Management IGD
        Route::get('/beds', function (\Illuminate\Http\Request $request) {
            $bedMgmtUrl = config('services.bed_management.base_url');

            if (empty($bedMgmtUrl)) {
                // Fallback mock data saat API belum terhubung
                $ruangan = $request->query('ruangan', '');
                $mockBeds = [
                    'IGD Umum'     => [['bed_id'=>'BED-IGD-01','bed_code'=>'IGD-01','status'=>'occupied'],['bed_id'=>'BED-IGD-02','bed_code'=>'IGD-02','status'=>'occupied']],
                    'ICU'          => [['bed_id'=>'ICU-01','bed_code'=>'ICU-01','status'=>'occupied']],
                    'Ruang Mawar'  => [['bed_id'=>'MWR-01','bed_code'=>'MWR-01','status'=>'occupied'],['bed_id'=>'MWR-02','bed_code'=>'MWR-02','status'=>'occupied']],
                ];
                return response()->json(['beds' => $mockBeds[$ruangan] ?? [], 'source' => 'mock']);
            }

            // Proxy ke Bed Management API dengan token
            $token = config('services.bed_management.token');
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->get("{$bedMgmtUrl}/api/beds", $request->query());

            return response()->json($response->json(), $response->status());
        });

        // Update status bed (dipanggil saat Batal Ranap diverifikasi OK)
        Route::post('/update-status', function (\Illuminate\Http\Request $request) {
            $validated = $request->validate([
                'bed_id'   => 'required|string',
                'ruangan'  => 'required|string',
                'status'   => 'required|in:available,occupied',
            ]);

            $bedMgmtUrl = config('services.bed_management.base_url');

            if (empty($bedMgmtUrl)) {
                // Log saja jika belum ada koneksi
                \Illuminate\Support\Facades\Log::info('Bed Management update (mock)', $validated);
                return response()->json(['success' => true, 'source' => 'mock', 'data' => $validated]);
            }

            $token = config('services.bed_management.token');
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->post("{$bedMgmtUrl}/api/beds/{$validated['bed_id']}/status", [
                    'status'    => $validated['status'],
                    'ruangan'   => $validated['ruangan'],
                    'timestamp' => now()->toISOString(),
                ]);

            return response()->json($response->json(), $response->status());
        });
    });
});
