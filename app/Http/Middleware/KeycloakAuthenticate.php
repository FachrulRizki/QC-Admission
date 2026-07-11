<?php

namespace App\Http\Middleware;

use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KeycloakAuthenticate
{
    public function __construct(private KeycloakService $keycloak) {}

    public function handle(Request $request, Closure $next)
    {
        $authUser = session('auth_user');
        if (! $authUser) {
            return $this->unauthenticated($request, 'Sesi tidak ditemukan. Silakan login kembali.');
        }

        $accessToken = session('keycloak_access_token');
        if (! $accessToken) {
            $request->session()->flush();
            return $this->unauthenticated($request, 'Token SSO tidak ditemukan. Silakan login ulang.');
        }

        // Introspect token ke Keycloak
        try {
            $tokenInfo = $this->keycloak->introspect($accessToken);
        } catch (\Throwable $e) {
            Log::warning('KeycloakAuthenticate: introspect gagal.', ['error' => $e->getMessage()]);
            return $next($request);
        }

        // Token masih aktif
        if (! empty($tokenInfo['active'])) {
            $this->maybeRefreshPermissions($request, $accessToken, $tokenInfo);
            return $next($request);
        }

        // Token expired → coba refresh dulu
        $refreshToken = session('keycloak_refresh_token', '');
        if ($refreshToken) {
            try {
                $refreshed = $this->keycloak->refreshToken($refreshToken);

                if (! empty($refreshed['access_token'])) {
                    $newAccessToken  = $refreshed['access_token'];
                    $newRefreshToken = $refreshed['refresh_token'] ?? $refreshToken;

                    // Hapus cache token lama
                    $this->keycloak->forgetTokenCache($accessToken);

                    // Introspect token baru
                    $newTokenInfo = $this->keycloak->introspect($newAccessToken);

                    if (! empty($newTokenInfo['active'])) {
                        $roles       = $this->keycloak->getRoles($newTokenInfo);
                        $permissions = $this->keycloak->getPermissions($newAccessToken);

                        $authUser['roles']                = $roles;
                        $authUser['permissions']          = $permissions;
                        $authUser['permissions_refreshed_at'] = now()->timestamp;

                        $request->session()->put('auth_user',               $authUser);
                        $request->session()->put('keycloak_access_token',   $newAccessToken);
                        $request->session()->put('keycloak_refresh_token',  $newRefreshToken);

                        return $next($request);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('KeycloakAuthenticate: token refresh gagal.', ['error' => $e->getMessage()]);
            }
        }

        $request->session()->flush();
        return $this->unauthenticated($request, 'Sesi SSO telah berakhir. Silakan login kembali.');
    }

    // refresh role 
    private function maybeRefreshPermissions(Request $request, string $accessToken, array $tokenInfo): void
    {
        $authUser    = session('auth_user', []);
        $lastRefresh = $authUser['permissions_refreshed_at'] ?? 0;

        // Refresh setiap 5 menit
        if ((now()->timestamp - $lastRefresh) < 300) {
            return;
        }

        try {
            $roles       = $this->keycloak->getRoles($tokenInfo);
            $permissions = $this->keycloak->getPermissions($accessToken);

            $authUser['roles']                    = $roles;
            $authUser['permissions']              = $permissions;
            $authUser['permissions_refreshed_at'] = now()->timestamp;

            $request->session()->put('auth_user', $authUser);
        } catch (\Throwable) {
            // Biarkan permissions lama jika refresh gagal
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
