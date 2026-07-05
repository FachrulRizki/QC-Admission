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

    /**
     * SSO Keycloak — validasi access_token ke userinfo endpoint, lalu buat/update user lokal.
     */
    public function ssoCallback(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        $keycloakBaseUrl = config('services.keycloak.base_url');
        $realm           = config('services.keycloak.realm', 'master');
        $userInfoUrl     = "{$keycloakBaseUrl}/realms/{$realm}/protocol/openid-connect/userinfo";

        // Validate token against Keycloak userinfo endpoint
        $ch = curl_init($userInfoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $request->access_token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false, // set to true in production
        ]);
        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $response) {
            return response()->json(['message' => 'Token SSO tidak valid.'], 401);
        }

        $keycloakUser = json_decode($response, true);
        if (! isset($keycloakUser['sub'])) {
            return response()->json(['message' => 'Data pengguna dari SSO tidak lengkap.'], 422);
        }

        // Find or create user
        $user = User::firstOrCreate(
            ['sso_id' => $keycloakUser['sub']],
            [
                'name'       => $keycloakUser['name'] ?? $keycloakUser['preferred_username'],
                'username'   => $keycloakUser['preferred_username'] ?? Str::slug($keycloakUser['name'] ?? 'user'),
                'email'      => $keycloakUser['email'] ?? $keycloakUser['preferred_username'] . '@rsud.sso',
                'password'   => Hash::make(Str::random(32)),
                'role'       => $this->mapKeycloakRole($keycloakUser),
                'login_type' => 'sso',
            ]
        );

        // Update name/email in case they changed in Keycloak
        $user->update([
            'name'       => $keycloakUser['name'] ?? $user->name,
            'login_type' => 'sso',
        ]);

        $token = $user->createToken('qc-admission-sso')->plainTextToken;

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
