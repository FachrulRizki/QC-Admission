<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QcAdmission\AuthController;
use App\Http\Controllers\QcAdmission\DashboardController;
use App\Http\Controllers\QcAdmission\QualityControlController;
use App\Http\Controllers\QcAdmission\BatalRanapController;
use App\Http\Controllers\QcAdmission\EdukasiLanjutanController;
use App\Http\Controllers\QcAdmission\UpSellingController;
use App\Http\Controllers\QcAdmission\PegawaiController;
use App\Http\Controllers\QcAdmission\PasienController;
use App\Http\Controllers\QcAdmission\MasterDataController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| QC Admission API Routes
|--------------------------------------------------------------------------
| Roles:
|   admin        — akses penuh semua menu + user management
|   qc_admission — entry QC, Edukasi Lanjutan, Up Selling, Batal Ranap
|   kasir        — view only Batal Ranap
*/

// ── Public ────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login',        [AuthController::class, 'login']);
    Route::post('/sso/callback', [AuthController::class, 'ssoCallback']);
});

// App config — login page pakai ini untuk tampil/sembunyikan tombol SSO
Route::get('/config', function () {
    return response()->json([
        'sso_enabled'     => (bool) config('services.sso_enabled', false),
        'rsus_db_enabled' => (bool) config('services.rsus_db_enabled', false),
    ]);
});

// ── Protected (Sanctum) ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Referensi — semua role
    Route::get('/master-data', [MasterDataController::class, 'index']);
    Route::get('/pegawai',     [PegawaiController::class,   'index']);
    Route::get('/pasien',      [PasienController::class,    'index']);

    // Log Aktivitas — admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/activity-log', [\App\Http\Controllers\QcAdmission\ActivityLogController::class, 'index']);
    });

    // Dashboard — admin + qc_admission
    Route::middleware('role:admin,qc_admission')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });

    // Quality Control — admin + qc_admission
    Route::middleware('role:admin,qc_admission')->group(function () {
        Route::apiResource('quality-control', QualityControlController::class);
    });

    // Edukasi Lanjutan — admin + qc_admission
    Route::middleware('role:admin,qc_admission')->group(function () {
        Route::apiResource('edukasi-lanjutan', EdukasiLanjutanController::class);
    // Sync status dari RSUS (manual trigger dari frontend)
    Route::get('edukasi-lanjutan/sync-rsus',  [EdukasiLanjutanController::class, 'syncRsus']);
    Route::get('edukasi-lanjutan-pending',    [EdukasiLanjutanController::class, 'pending']);
    });

    // Up Selling — admin + qc_admission
    Route::middleware('role:admin,qc_admission')->group(function () {
        Route::apiResource('up-selling', UpSellingController::class);
    });

    // Batal Ranap — semua role bisa GET, kasir hanya GET
    Route::get('batal-ranap',      [BatalRanapController::class, 'index']);
    Route::get('batal-ranap/{id}', [BatalRanapController::class, 'show']);
    Route::middleware('role:admin,qc_admission')->group(function () {
        Route::post  ('batal-ranap',                     [BatalRanapController::class, 'store']);
        Route::put   ('batal-ranap/{id}',                [BatalRanapController::class, 'update']);
        Route::delete('batal-ranap/{id}',                [BatalRanapController::class, 'destroy']);
        Route::patch ('batal-ranap/{id}/verifikasi',     [BatalRanapController::class, 'verifikasi']);
    });

    // User management — admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', function () {
            return response()->json(
                User::select('id','name','username','email','role','login_type','created_at')->get()
            );
        });
        Route::post('/users', function (Request $request) {
            $data = $request->validate([
                'name'     => 'required|string|max:100',
                'username' => 'required|string|unique:users|max:50',
                'email'    => 'required|email|unique:users',
                'password' => 'required|string|min:6',
                'role'     => 'required|in:admin,qc_admission,kasir',
            ]);
            $user = User::create(array_merge($data, [
                'password'   => Hash::make($data['password']),
                'login_type' => 'local',
            ]));
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
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }
            $user->update($data);
            return response()->json(['user' => $user]);
        });
        Route::delete('/users/{id}', function ($id) {
            User::findOrFail($id)->delete();
            return response()->json(['message' => 'User dihapus.']);
        });
    });

    // Bed Management IGD proxy
    Route::prefix('bed-management')->group(function () {
        Route::get('/beds', function (Request $request) {
            $url = config('services.bed_management.base_url');
            if (empty($url)) {
                $ruangan  = $request->query('ruangan', '');
                $mock = [
                    'IGD Umum'    => [['bed_id'=>'BED-IGD-01','bed_code'=>'IGD-01','status'=>'occupied'],['bed_id'=>'BED-IGD-02','bed_code'=>'IGD-02','status'=>'occupied']],
                    'ICU'         => [['bed_id'=>'ICU-01','bed_code'=>'ICU-01','status'=>'occupied']],
                    'Ruang Mawar' => [['bed_id'=>'MWR-01','bed_code'=>'MWR-01','status'=>'occupied'],['bed_id'=>'MWR-02','bed_code'=>'MWR-02','status'=>'occupied']],
                ];
                return response()->json(['beds' => $mock[$ruangan] ?? [], 'source' => 'mock']);
            }
            $resp = \Illuminate\Support\Facades\Http::withToken(config('services.bed_management.token'))
                ->get("{$url}/api/beds", $request->query());
            return response()->json($resp->json(), $resp->status());
        });
        Route::post('/update-status', function (Request $request) {
            $v = $request->validate([
                'bed_id'  => 'required|string',
                'ruangan' => 'required|string',
                'status'  => 'required|in:available,occupied',
            ]);
            $url = config('services.bed_management.base_url');
            if (empty($url)) {
                \Illuminate\Support\Facades\Log::info('Bed Management update (mock)', $v);
                return response()->json(['success' => true, 'source' => 'mock', 'data' => $v]);
            }
            $resp = \Illuminate\Support\Facades\Http::withToken(config('services.bed_management.token'))
                ->post("{$url}/api/beds/{$v['bed_id']}/status", [
                    'status'    => $v['status'],
                    'ruangan'   => $v['ruangan'],
                    'timestamp' => now()->toISOString(),
                ]);
            return response()->json($resp->json(), $resp->status());
        });
    });
});
