<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = session('auth_user');

        if (! $user) {
            return response()->json(['user' => null, 'authenticated' => false]);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'          => $user['id']          ?? null,
                'name'        => $user['name']        ?? null,
                'email'       => $user['email']       ?? null,
                'username'    => $user['username']    ?? null,
                'roles'       => $user['roles']       ?? [],
                'permissions' => $user['permissions'] ?? [],
                'login_type'  => $user['login_type']  ?? 'sso',
            ],
        ]);
    }

    /**
     * Login lokal — digunakan saat SSO_ENABLED=false untuk testing.
     */
    public function localLogin(Request $request): JsonResponse
    {
        // Blok jika SSO aktif di production
        if (config('services.sso_enabled', true)) {
            return response()->json(['message' => 'Login lokal tidak tersedia. Gunakan SSO.'], 403);
        }

        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $data['username'])
            ->orWhere('email', $data['username'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Username atau password salah.'], 401);
        }

        // Bangun auth_user session — format sama dengan SSO agar middleware/store tidak perlu berubah
        $roles = [$user->role];

        // Derived permissions dari role (minimal set untuk testing)
        $permissions = $this->derivePermissions($user->role);

        $request->session()->regenerate();
        $request->session()->put('auth_user', [
            'id'                      => $user->id,
            'name'                    => $user->name,
            'email'                   => $user->email,
            'username'                => $user->username,
            'roles'                   => $roles,
            'permissions'             => $permissions,
            'login_type'              => 'local',
            'permissions_refreshed_at'=> now()->timestamp,
        ]);

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'username'    => $user->username,
                'roles'       => $roles,
                'permissions' => $permissions,
                'login_type'  => 'local',
            ],
        ]);
    }

    /**
     * Logout lokal — clear session saja, tidak perlu back-channel Keycloak.
     */
    public function localLogout(Request $request): JsonResponse
    {
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function derivePermissions(string $role): array
    {
        return match ($role) {
            'admin' => [
                'quality-control:read', 'quality-control:write',
                'batal-ranap:read',     'batal-ranap:write',
                'edukasi-lanjutan:read','edukasi-lanjutan:write',
                'up-selling:read',      'up-selling:write',
                'master-data:read',     'master-data:write',
                'activity-log:read',
                'user-management:read', 'user-management:write',
                'bed-management:read',  'bed-management:write',
            ],
            'qc_admission' => [
                'quality-control:read', 'quality-control:write',
                'batal-ranap:read',     'batal-ranap:write',
                'edukasi-lanjutan:read','edukasi-lanjutan:write',
                'up-selling:read',      'up-selling:write',
                'bed-management:read',  'bed-management:write',
            ],
            'kasir' => [
                'batal-ranap:read',
                'bed-management:read',
            ],
            default => [],
        };
    }
}
