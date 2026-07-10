<?php

use App\Http\Controllers\Auth\KeycloakController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Login lokal — via web route agar session tersedia
Route::post('/auth/login',  [\App\Http\Controllers\QcAdmission\AuthController::class, 'login'])->name('auth.login');
Route::post('/auth/logout', [\App\Http\Controllers\QcAdmission\AuthController::class, 'logout'])->name('auth.logout');
Route::get ('/auth/me',     [\App\Http\Controllers\QcAdmission\AuthController::class, 'me'])->name('auth.me');

// SSO Keycloak via Socialite
Route::get('/auth/keycloak/redirect', [KeycloakController::class, 'redirect'])->name('keycloak.redirect');
Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('keycloak.callback');
Route::post('/auth/keycloak/logout',  [KeycloakController::class, 'logout'])->name('keycloak.logout');

// Halaman publik
Route::get('/',          fn () => redirect()->route('login'));
Route::get('/login',     function () {
    // Kalau sudah login, langsung ke dashboard
    if (session('auth_user')) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('login');
})->name('login');
Route::get('/akses-ditolak', fn () => Inertia::render('akses-ditolak'))->name('akses.ditolak');

// Halaman terproteksi
Route::middleware(['keycloak.auth'])->group(function () {
    Route::get('/dashboard',        fn () => Inertia::render('dashboard'))->name('dashboard');
    Route::get('/quality-control',  fn () => Inertia::render('qc_admission/quality-control'));
    Route::get('/edukasi-lanjutan', fn () => Inertia::render('qc_admission/edukasi-lanjutan'));
    Route::get('/batal-ranap',      fn () => Inertia::render('qc_admission/batal-ranap'));
    Route::get('/batal-ranap-view', fn () => Inertia::render('qc_admission/batal-ranap-view'));
    Route::get('/up-selling',       fn () => Inertia::render('qc_admission/up-selling'));
    Route::get('/view-data-input',  fn () => Inertia::render('qc_admission/view-data-input'));

    Route::middleware(['keycloak.role:admin'])->group(function () {
        Route::get('/activity-log',    fn () => Inertia::render('qc_admission/activity-log'))->name('activity-log');
        Route::get('/master-data',     fn () => Inertia::render('qc_admission/master-data'))->name('master-data');
        Route::get('/user-management', fn () => Inertia::render('qc_admission/user-management'))->name('user-management');
    });
});

// Catch-all 404
Route::get('/{any}', fn () => Inertia::render('error/404'))->where('any', '.*');
