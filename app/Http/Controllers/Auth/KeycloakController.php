<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\KeycloakService;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KeycloakController extends Controller
{
    public function __construct(private KeycloakService $keycloak) {}

    public function redirect()
    {
        return Socialite::driver('keycloak')
            ->with(['prompt' => 'login'])
            ->redirect();
    }

    /**
     * Callback dari Keycloak setelah user login.
     */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/login')->with('error',
                $request->query('error_description') ?? 'Login SSO gagal.'
            );
        }

        try {
            $socialUser = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            Log::error('Keycloak callback error.', ['error' => $e->getMessage()]);
            return redirect('/login')->with('error', 'Gagal autentikasi dengan Keycloak.');
        }

        $accessToken  = $socialUser->token;
        $refreshToken = $socialUser->refreshToken ?? '';

        // Introspect token
        try {
            $introspected = $this->keycloak->introspect($accessToken);
        } catch (\Throwable $e) {
            Log::error('Keycloak introspect error.', ['error' => $e->getMessage()]);
            return redirect('/login')->with('error', 'Tidak dapat memvalidasi token SSO.');
        }

        if (empty($introspected['active'])) {
            return redirect('/login')->with('error', 'Token SSO tidak valid.');
        }

        $roles = $this->keycloak->getRoles($introspected);

        // Log untuk debug (hanya saat APP_DEBUG=true)
        Log::debug('Keycloak SSO login', [
            'username'        => $socialUser->getNickname(),
            'roles'           => $roles,
            'resource_access' => array_keys($introspected['resource_access'] ?? []),
        ]);

        if (empty($roles)) {
            Log::warning('SSO ditolak: roles kosong.', [
                'username'    => $socialUser->getNickname(),
                'client_id'   => config('services.keycloak.client_id'),
                'realm_roles' => $introspected['realm_access']['roles'] ?? [],
                'clients'     => array_keys($introspected['resource_access'] ?? []),
                'hint'        => 'Assign role admin/qc_admission/kasir ke user di client ' . config('services.keycloak.client_id') . ' di Keycloak Admin Console.',
            ]);

            // Simpan info user ke session sementara untuk ditampilkan di halaman
            $request->session()->put('sso_rejected_user', [
                'name'      => $socialUser->getName() ?? $socialUser->getNickname(),
                'username'  => $socialUser->getNickname(),
                'email'     => $socialUser->getEmail() ?? '',
                'client_id' => config('services.keycloak.client_id'),
            ]);

            return redirect('/akses-ditolak');
        }

        $permissions = $this->keycloak->getPermissions($accessToken);

        $session = [
            'id'          => $socialUser->getId(),
            'name'        => $socialUser->getName() ?? $socialUser->getNickname(),
            'email'       => $socialUser->getEmail() ?? '',
            'username'    => $socialUser->getNickname(),
            'roles'       => $roles,
            'permissions' => $permissions,
            'login_type'  => 'sso',
        ];

        $request->session()->regenerate();
        $request->session()->put('auth_user', $session);
        $request->session()->put('keycloak_access_token',  $accessToken);
        $request->session()->put('keycloak_refresh_token', $refreshToken);

        ActivityLog::record('auth', 'login',
            "SSO login — {$session['name']} [" . implode(', ', $roles) . "]"
        );

        $default = in_array('kasir', $roles) ? '/view-data-input' : '/dashboard';
        return redirect()->intended($default);
    }

    /**
     * Logout — back-channel ke Keycloak + clear session.
     */
    public function logout(Request $request)
    {
        $accessToken  = session('keycloak_access_token');
        $refreshToken = session('keycloak_refresh_token');
        $userName     = session('auth_user.name', 'Unknown');

        ActivityLog::record('auth', 'logout', "SSO logout — {$userName}");

        if ($accessToken) {
            $this->keycloak->forgetTokenCache($accessToken);
        }
        if ($refreshToken) {
            try { $this->keycloak->logout($refreshToken); } catch (\Throwable) {}
        }

        $request->session()->flush();
        return redirect('/login');
    }
}
