<?php

namespace App\Http\Middleware;

use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KeycloakAuthenticate
{
    // Interval cek ulang token + role ke Keycloak (detik)
    private const RECHECK_INTERVAL = 300;
    private const UNREACHABLE_TTL = 60;
    private const UNREACHABLE_KEY = 'kc_unreachable';

    public function __construct(private KeycloakService $keycloak) {}

    public function handle(Request $request, Closure $next)
    {
        $authUser = session('auth_user');
        if (! $authUser) {
            return $this->unauthenticated($request, 'Sesi tidak ditemukan. Silakan login kembali.');
        }

        // Jika user login secara lokal, tidak perlu validasi token Keycloak.
        if (($authUser['login_type'] ?? 'sso') === 'local') {
            return $next($request);
        }

        $accessToken = session('keycloak_access_token');
        if (! $accessToken) {
            $request->session()->flush();
            return $this->unauthenticated($request, 'Token SSO tidak ditemukan. Silakan login ulang.');
        }

        // ── Fast path: token belum expired → validasi lokal, tanpa HTTP ke Keycloak
        $exp = session('keycloak_token_exp') ?? ($this->keycloak->decodeToken($accessToken)['exp'] ?? 0);
        if ($exp > now()->timestamp + 10) {
            $this->maybeRecheck($request, $accessToken);
            return $next($request);
        }

        // Keycloak sedang tidak bisa dijangkau → loloskan dulu (sama seperti sebelumnya)
        if (Cache::has(self::UNREACHABLE_KEY)) {
            return $next($request);
        }

        // ── Token expired → refresh
        $refreshToken = session('keycloak_refresh_token', '');
        if ($refreshToken) {
            try {
                $refreshed = $this->keycloak->refreshToken($refreshToken);

                if (! empty($refreshed['access_token'])) {
                    $this->keycloak->forgetTokenCache($accessToken);
                    $this->storeToken($request, $refreshed['access_token'], $refreshed['refresh_token'] ?? $refreshToken);
                    return $next($request);
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::warning('KeycloakAuthenticate: Keycloak tidak bisa dijangkau.', ['error' => $e->getMessage()]);
                Cache::put(self::UNREACHABLE_KEY, true, self::UNREACHABLE_TTL);
                return $next($request);
            } catch (\Throwable $e) {
                Log::warning('KeycloakAuthenticate: token refresh gagal.', ['error' => $e->getMessage()]);
            }
        }

        $request->session()->flush();
        return $this->unauthenticated($request, 'Sesi SSO telah berakhir. Silakan login kembali.');
    }

    /**
     * Simpan token baru + role/permission dari claims-nya ke session.
     */
    private function storeToken(Request $request, string $accessToken, string $refreshToken): void
    {
        $claims   = $this->keycloak->tokenClaims($accessToken);
        $authUser = session('auth_user', []);

        if (! empty($claims['active'])) {
            $authUser['roles']       = $this->keycloak->getRoles($claims);
            $authUser['permissions'] = $this->keycloak->extractPermissions($claims);
        }
        $authUser['permissions_refreshed_at'] = now()->timestamp;

        $request->session()->put('auth_user',              $authUser);
        $request->session()->put('keycloak_access_token',  $accessToken);
        $request->session()->put('keycloak_refresh_token', $refreshToken);
        $request->session()->put('keycloak_token_exp',     $claims['exp'] ?? null);
    }

    /**
     * Setiap 5 menit: introspect ke Keycloak untuk deteksi sesi yang di-revoke
     * dan ambil role/permission terbaru.
     */
    private function maybeRecheck(Request $request, string $accessToken): void
    {
        $authUser    = session('auth_user', []);
        $lastRefresh = $authUser['permissions_refreshed_at'] ?? 0;

        if ((now()->timestamp - $lastRefresh) < self::RECHECK_INTERVAL || Cache::has(self::UNREACHABLE_KEY)) {
            return;
        }

        // Catat waktu cek dulu, supaya kegagalan tidak membuat setiap request mencoba ulang
        $authUser['permissions_refreshed_at'] = now()->timestamp;
        $request->session()->put('auth_user', $authUser);

        try {
            $this->keycloak->forgetTokenCache($accessToken);
            $this->keycloak->forgetPermissionsCache($accessToken);

            $tokenInfo = $this->keycloak->introspect($accessToken);

            if (empty($tokenInfo['active'])) {
                // Sesi di-revoke di Keycloak → paksa jalur refresh di request berikutnya
                $request->session()->put('keycloak_token_exp', 0);
                return;
            }

            $authUser['roles']       = $this->keycloak->getRoles($tokenInfo);
            $authUser['permissions'] = $this->keycloak->extractPermissions($tokenInfo);
            $request->session()->put('auth_user', $authUser);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Cache::put(self::UNREACHABLE_KEY, true, self::UNREACHABLE_TTL);
        } catch (\Throwable) {
        }
    }

    private function unauthenticated(Request $request, string $message): mixed
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 401);
        }

        return redirect('/login')->with('error', $message);
    }
}
