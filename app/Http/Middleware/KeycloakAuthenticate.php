<?php

namespace App\Http\Middleware;

use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;

class KeycloakAuthenticate
{
    public function __construct(private KeycloakService $keycloak) {}

    public function handle(Request $request, Closure $next)
    {
        if (!session('auth_user')) {
            return redirect('/login');
        }

        $accessToken = session('keycloak_access_token');

        // Login lokal — tidak punya Keycloak token, langsung lanjut
        if (!$accessToken) {
            return $next($request);
        }

        // Login SSO — introspect token ke Keycloak
        try {
            $userInfo = $this->keycloak->introspect($accessToken);
        } catch (\Throwable) {
            return redirect('/login')->with('error', 'Layanan autentikasi tidak tersedia.');
        }

        if (!empty($userInfo['active'])) {
            return $next($request);
        }

        // Token tidak aktif (expired / di-revoke dari Keycloak) → paksa login ulang
        $request->session()->flush();
        return redirect('/login');
    }
}
