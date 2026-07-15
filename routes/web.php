<?php

use App\Http\Controllers\Auth\KeycloakController;
use App\Http\Controllers\QcAdmission\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Auth SSO Keycloak ─────────────────────────────────────────────────────────
// Semua auth melalui Keycloak SSO — tidak ada login lokal
Route::get ('/auth/keycloak/redirect', [KeycloakController::class, 'redirect'])->name('keycloak.redirect');
Route::get ('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('keycloak.callback');
Route::post('/auth/keycloak/logout',   [KeycloakController::class, 'logout'])->name('keycloak.logout');

// ── Auth Lokal (hanya aktif saat SSO_ENABLED=false) ───────────────────────────
Route::post('/auth/local/login',  [AuthController::class, 'localLogin'])->name('auth.local.login');
Route::post('/auth/local/logout', [AuthController::class, 'localLogout'])->name('auth.local.logout');

// Alias logout untuk frontend — cek login_type untuk pilih handler
Route::post('/auth/logout', function (\Illuminate\Http\Request $request) {
    $loginType = session('auth_user.login_type', 'sso');
    if ($loginType === 'local') {
        return app(AuthController::class)->localLogout($request);
    }
    return app(KeycloakController::class)->logout($request);
})->name('auth.logout');

// Session info (non-sensitive, untuk debug / health check)
Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

// ── Halaman publik ────────────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', function () {
    // Sudah login → redirect ke halaman default
    if (session('auth_user')) {
        $roles = session('auth_user.roles', []);
        return redirect(in_array('kasir', $roles) ? '/view-data-input' : '/dashboard');
    }
    return Inertia::render('login');
})->name('login');

Route::get('/akses-ditolak', function () {
    // Ambil data user yang ditolak, lalu hapus dari session (one-time display)
    $rejected = session()->pull('sso_rejected_user');
    return Inertia::render('akses-ditolak', ['ssoRejectedUser' => $rejected]);
})->name('akses.ditolak');

// ── Halaman terproteksi ───────────────────────────────────────────────────────
Route::middleware(['keycloak.auth'])->group(function () {

    // Semua user terautentikasi — tidak butuh permission khusus
    Route::get('/view-data-input',  fn () => Inertia::render('qc_admission/view-data-input'));
    Route::get('/batal-ranap-view', fn () => Inertia::render('qc_admission/batal-ranap-view'));

    // Butuh permission dashboard:view
    Route::middleware(['keycloak.role:dashboard:view'])->group(function () {
        Route::get('/dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
    });

    // Butuh permission quality-control:view
    Route::middleware(['keycloak.role:quality-control:view'])->group(function () {
        Route::get('/quality-control', fn () => Inertia::render('qc_admission/quality-control'));
    });

    // Butuh permission edukasi-lanjutan:view
    Route::middleware(['keycloak.role:edukasi-lanjutan:view'])->group(function () {
        Route::get('/edukasi-lanjutan', fn () => Inertia::render('qc_admission/edukasi-lanjutan'));
    });

    // Butuh permission batal-ranap:view
    Route::middleware(['keycloak.role:batal-ranap:view'])->group(function () {
        Route::get('/batal-ranap', fn () => Inertia::render('qc_admission/batal-ranap'));
    });

    // Butuh permission up-selling:view
    Route::middleware(['keycloak.role:up-selling:view'])->group(function () {
        Route::get('/up-selling', fn () => Inertia::render('qc_admission/up-selling'));
    });

    // Butuh permission activity-log:view
    Route::middleware(['keycloak.role:activity-log:view'])->group(function () {
        Route::get('/activity-log', fn () => Inertia::render('qc_admission/activity-log'))->name('activity-log');
    });

    // Butuh permission master-data:view
    Route::middleware(['keycloak.role:master-data:view'])->group(function () {
        Route::get('/master-data', fn () => Inertia::render('qc_admission/master-data'))->name('master-data');
    });

    // Butuh permission user-management:view
    Route::middleware(['keycloak.role:user-management:view'])->group(function () {
        Route::get('/user-management', fn () => Inertia::render('qc_admission/user-management'))->name('user-management');
    });
});

// Catch-all 404
Route::get('/{any}', fn () => Inertia::render('error/404'))->where('any', '.*');
