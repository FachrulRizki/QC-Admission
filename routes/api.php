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
use Illuminate\Support\Facades\DB;

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
        'sso_enabled'       => (bool) config('services.sso_enabled', false),
        'rsus_db_enabled'   => (bool) config('services.rsus_db_enabled', false),
        // Keycloak config diekspos ke frontend (non-secret — hanya base_url, realm, client_id)
        'keycloak_base_url' => config('services.keycloak.base_url', ''),
        'keycloak_realm'    => config('services.keycloak.realm', 'master'),
        'keycloak_client_id'=> config('services.keycloak.client_id', 'qc-admission'),
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

    // Master Data CRUD — admin only
    Route::middleware('role:admin')->group(function () {
        Route::post  ('/master-data',           [MasterDataController::class, 'store']);
        Route::put   ('/master-data/{category}', [MasterDataController::class, 'update']);
        Route::delete('/master-data/{category}/{index}', [MasterDataController::class, 'destroy']);
    });

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
        // ⚠️ Route spesifik HARUS didaftarkan SEBELUM apiResource
        // agar tidak tertimpa oleh pattern {quality_control}
        Route::post('quality-control/process-edukasi-lanjutan', function () {
            try {
                \Illuminate\Support\Facades\Artisan::call('qc:process-edukasi-lanjutan');
                $output = trim(\Illuminate\Support\Facades\Artisan::output());
                $count  = 0;
                if (preg_match('/(\d+)\s+pasien\s+dipindahkan/', $output, $m)) {
                    $count = (int) $m[1];
                }
                return response()->json(['success' => true, 'output' => $output, 'count' => $count]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'count' => 0], 500);
            }
        });

        Route::apiResource('quality-control', QualityControlController::class);
        Route::patch('quality-control/{id}/ranap', [QualityControlController::class, 'updateRanap']);
    });

    // Edukasi Lanjutan — admin + qc_admission
    Route::middleware('role:admin,qc_admission')->group(function () {
        // ⚠️ Route spesifik SEBELUM apiResource
        Route::get('edukasi-lanjutan/sync-rsus', [EdukasiLanjutanController::class, 'syncRsus']);
        Route::get('edukasi-lanjutan-pending',   [EdukasiLanjutanController::class, 'pending']);

        Route::apiResource('edukasi-lanjutan', EdukasiLanjutanController::class);
        Route::patch('edukasi-lanjutan/{id}/ranap', [EdukasiLanjutanController::class, 'updateRanap']);
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
        Route::patch ('batal-ranap/{id}/verifikasi',          [BatalRanapController::class, 'verifikasi']);
        Route::patch ('batal-ranap/{id}/konfirmasi-closing',  [BatalRanapController::class, 'konfirmasiClosing']);
        Route::get   ('batal-ranap/{id}/bed-history',         [BatalRanapController::class, 'bedHistory']);
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
            \App\Models\ActivityLog::record('user', 'create', "User baru: {$user->name} ({$user->role})");
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
            \App\Models\ActivityLog::record('user', 'update', "User diupdate: {$user->name} ({$user->role})");
            return response()->json(['user' => $user]);
        });
        Route::delete('/users/{id}', function ($id) {
            $user = User::findOrFail($id);
            \App\Models\ActivityLog::record('user', 'delete', "User dihapus: {$user->name} ({$user->role})");
            $user->delete();
            return response()->json(['message' => 'User dihapus.']);
        });
    });

    // Bed Management IGD proxy — baca dari BI_Bed_Igd RSUS jika DB aktif
    Route::prefix('bed-management')->group(function () {
        Route::get('/beds', function (Request $request) {
            $ruangan = $request->query('ruangan', '');

            // Jika RSUS aktif, baca dari tabel BI_Bed_Igd
            if (config('services.rsus_db_enabled', false)) {
                try {
                    $query = \Illuminate\Support\Facades\DB::connection('rsus')
                        ->table('BI_Bed_Igd');

                    if ($ruangan !== '') {
                        $query->where('Nama_Ruang', $ruangan);
                    }

                    $rows = $query->get();

                    $beds = $rows->map(fn($r) => [
                        'bed_id'   => $r->No_Bed   ?? $r->bed_id   ?? $r->Kode_Bed ?? null,
                        'bed_code' => $r->No_Bed   ?? $r->bed_code ?? $r->Kode_Bed ?? null,
                        'ruangan'  => $r->Nama_Ruang ?? $ruangan,
                        'status'   => $r->Status   ?? $r->status   ?? 'available',
                        'pasien'   => $r->Nama_Pasien ?? null,
                    ])->values();

                    return response()->json(['beds' => $beds, 'source' => 'rsus_db']);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('BI_Bed_Igd query failed: ' . $e->getMessage());
                }
            }

            // Fallback ke external API
            $url = config('services.bed_management.base_url');
            if (!empty($url)) {
                $resp = \Illuminate\Support\Facades\Http::withToken(config('services.bed_management.token'))
                    ->get("{$url}/api/beds", $request->query());
                return response()->json($resp->json(), $resp->status());
            }

            // Mock data
            $mock = [
                'IGD Umum'    => [['bed_id'=>'BED-IGD-01','bed_code'=>'IGD-01','status'=>'occupied'],['bed_id'=>'BED-IGD-02','bed_code'=>'IGD-02','status'=>'available']],
                'ICU'         => [['bed_id'=>'ICU-01','bed_code'=>'ICU-01','status'=>'occupied']],
                'Ruang Mawar' => [['bed_id'=>'MWR-01','bed_code'=>'MWR-01','status'=>'available'],['bed_id'=>'MWR-02','bed_code'=>'MWR-02','status'=>'occupied']],
                'IGD Bedah'   => [['bed_id'=>'BED-BGH-01','bed_code'=>'BGH-01','status'=>'available'],['bed_id'=>'BED-BGH-02','bed_code'=>'BGH-02','status'=>'available']],
            ];
            return response()->json(['beds' => $mock[$ruangan] ?? [], 'source' => 'mock']);
        });

        Route::post('/update-status', function (Request $request) {
            $v = $request->validate([
                'bed_id'  => 'required|string',
                'ruangan' => 'required|string',
                'status'  => 'required|in:available,occupied',
            ]);

            // Update di RSUS jika aktif
            if (config('services.rsus_db_enabled', false)) {
                try {
                    \Illuminate\Support\Facades\DB::connection('rsus')
                        ->table('BI_Bed_Igd')
                        ->where('No_Bed', $v['bed_id'])
                        ->update(['Status' => $v['status'] === 'available' ? 'Kosong' : 'Terisi', 'updated_at' => now()]);
                    return response()->json(['success' => true, 'source' => 'rsus_db', 'data' => $v]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('BI_Bed_Igd update failed: ' . $e->getMessage());
                }
            }

            $url = config('services.bed_management.base_url');
            if (!empty($url)) {
                $resp = \Illuminate\Support\Facades\Http::withToken(config('services.bed_management.token'))
                    ->post("{$url}/api/beds/{$v['bed_id']}/status", [
                        'status'    => $v['status'],
                        'ruangan'   => $v['ruangan'],
                        'timestamp' => now()->toISOString(),
                    ]);
                return response()->json($resp->json(), $resp->status());
            }

            \Illuminate\Support\Facades\Log::info('Bed Management update (mock)', $v);
            return response()->json(['success' => true, 'source' => 'mock', 'data' => $v]);
        });
    });
});
