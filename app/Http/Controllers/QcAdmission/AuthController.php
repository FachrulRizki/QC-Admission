<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $field = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $credentials['username'], 'password' => $credentials['password']])) {
            return response()->json(['message' => 'Username atau password salah.'], 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('qc-admission')->plainTextToken;

        ActivityLog::record('auth', 'login', "Login berhasil — {$user->name}");

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function ssoCallback(Request $request): JsonResponse
    {
        $keycloakBaseUrl = config('services.keycloak.base_url');
        $realm           = config('services.keycloak.realm', 'master');

        // ── Mode 1: Authorization Code Flow ──────────────────────────────────
        if ($request->has('code')) {
            $request->validate([
                'code'         => 'required|string',
                'redirect_uri' => 'required|string',
            ]);

            // Tukar code → access_token via Keycloak token endpoint
            $tokenEndpoint = "{$keycloakBaseUrl}/realms/{$realm}/protocol/openid-connect/token";

            $ch = curl_init($tokenEndpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type'    => 'authorization_code',
                    'client_id'     => config('services.keycloak.client_id'),
                    'client_secret' => config('services.keycloak.client_secret'),
                    'code'          => $request->code,
                    'redirect_uri'  => $request->redirect_uri,
                ]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $tokenResponse = curl_exec($ch);
            $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($tokenHttpCode !== 200 || ! $tokenResponse) {
                \Illuminate\Support\Facades\Log::warning('Keycloak token exchange failed', [
                    'http_code' => $tokenHttpCode,
                    'response'  => $tokenResponse,
                ]);
                return response()->json(['message' => 'Gagal menukar code ke token Keycloak.'], 401);
            }

            $tokenData   = json_decode($tokenResponse, true);
            $accessToken = $tokenData['access_token'] ?? null;

            if (! $accessToken) {
                return response()->json(['message' => 'Access token tidak ditemukan dari Keycloak.'], 422);
            }
        }
        // ── Mode 2: Direct access_token (legacy) ──────────────────────────────
        elseif ($request->has('access_token')) {
            $request->validate(['access_token' => 'required|string']);
            $accessToken = $request->access_token;
        } else {
            return response()->json(['message' => 'Parameter code atau access_token wajib ada.'], 422);
        }

        // ── Ambil info user dari Keycloak userinfo endpoint ───────────────────
        $userInfoUrl = "{$keycloakBaseUrl}/realms/{$realm}/protocol/openid-connect/userinfo";

        $ch = curl_init($userInfoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $userInfoResponse = curl_exec($ch);
        $userInfoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($userInfoHttpCode !== 200 || ! $userInfoResponse) {
            return response()->json(['message' => 'Token SSO tidak valid atau sudah kedaluwarsa.'], 401);
        }

        $keycloakUser = json_decode($userInfoResponse, true);
        if (! isset($keycloakUser['sub'])) {
            return response()->json(['message' => 'Data pengguna dari SSO tidak lengkap.'], 422);
        }

        // ── Buat / update user lokal ──────────────────────────────────────────
        $user = User::firstOrCreate(
            ['sso_id' => $keycloakUser['sub']],
            [
                'name'       => $keycloakUser['name']               ?? $keycloakUser['preferred_username'],
                'username'   => $keycloakUser['preferred_username']  ?? Str::slug($keycloakUser['name'] ?? 'user'),
                'email'      => $keycloakUser['email']               ?? ($keycloakUser['preferred_username'] . '@rsud.sso'),
                'password'   => Hash::make(Str::random(32)),
                'role'       => $this->mapKeycloakRole($keycloakUser),
                'login_type' => 'sso',
            ]
        );

        $user->update([
            'name'       => $keycloakUser['name'] ?? $user->name,
            'login_type' => 'sso',
        ]);

        $token = $user->createToken('qc-admission-sso')->plainTextToken;

        ActivityLog::record('auth', 'sso-login', "SSO Login — {$user->name}");

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        ActivityLog::record('auth', 'logout', "Logout — {$request->user()->name}");
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    /** Map Keycloak realm roles ke role lokal. */
    private function mapKeycloakRole(array $keycloakUser): string
    {
        $realmRoles = $keycloakUser['realm_access']['roles'] ?? [];

        if (in_array('admin', $realmRoles) || in_array('qc_admin', $realmRoles)) return 'admin';
        if (in_array('kasir', $realmRoles)) return 'kasir';

        return 'qc_admission';
    }
}
