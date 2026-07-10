<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\KeycloakService;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class KeycloakController extends Controller
{
    public function __construct(private KeycloakService $keycloak) {}

    public function redirect()
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/login')->with('error', $request->error_description ?? 'Login SSO gagal.');
        }

        try {
            $socialUser = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            return redirect('/login')->with('error', 'Gagal autentikasi dengan Keycloak: ' . $e->getMessage());
        }

        $accessToken  = $socialUser->token;
        $refreshToken = $socialUser->refreshToken;

        // Introspect untuk ambil roles
        try {
            $introspected = $this->keycloak->introspect($accessToken);
        } catch (\Throwable) {
            $introspected = [];
        }

        $roles = $this->keycloak->getRoles($introspected);

        session([
            'auth_user' => [
                'id'       => $socialUser->getId(),
                'name'     => $socialUser->getName() ?? $socialUser->getNickname(),
                'email'    => $socialUser->getEmail() ?? '',
                'username' => $socialUser->getNickname(),
                'roles'    => $roles,
            ],
            'keycloak_access_token'  => $accessToken,
            'keycloak_refresh_token' => $refreshToken,
        ]);

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request)
    {
        $accessToken  = session('keycloak_access_token');
        $refreshToken = session('keycloak_refresh_token');

        if ($accessToken) {
            $this->keycloak->forgetTokenCache($accessToken);
        }

        if ($refreshToken) {
            try {
                $this->keycloak->logout($refreshToken);
            } catch (\Throwable) {}
        }

        $request->session()->flush();
        return redirect('/login');
    }
}
