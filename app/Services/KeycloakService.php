<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class KeycloakService
{
    private string $baseUrl;
    private string $realm;
    private string $clientId;
    private string $clientSecret;
    private int    $timeout;
    private int    $cacheTtl;

    public function __construct()
    {
        $this->baseUrl      = config('services.keycloak.base_url', '');
        $this->realm        = config('services.keycloak.realm', 'master');
        $this->clientId     = config('services.keycloak.client_id', '');
        $this->clientSecret = config('services.keycloak.client_secret', '');
        $this->timeout      = (int) config('services.keycloak.timeout', 5);
        $this->cacheTtl     = (int) config('services.keycloak.cache_ttl', 60);
    }

    // URL helpers

    private function tokenUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/token";
    }

    private function introspectUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/token/introspect";
    }

    private function logoutUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/logout";
    }

    private function userinfoUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/userinfo";
    }

    // Introspect

    /**
     * Introspect access token ke Keycloak (cached).
     */
    public function introspect(string $accessToken): array
    {
        $cacheKey = 'kc_token:' . hash('sha256', $accessToken);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($accessToken) {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post($this->introspectUrl(), ['token' => $accessToken]);

            return $response->json() ?? [];
        });
    }

    public function forgetTokenCache(string $accessToken): void
    {
        Cache::forget('kc_token:' . hash('sha256', $accessToken));
    }

    // Roles
    public function getRoles(array $introspection): array
    {
        $systemRoles = [
            'uma_authorization',
            'offline_access',
            'default-roles-' . $this->realm,
            'manage-account',
            'manage-account-links',
            'view-profile',
        ];

        $clientRoles = $introspection['resource_access'][$this->clientId]['roles'] ?? [];
        $realmRoles = $introspection['realm_access']['roles'] ?? [];

        $all = array_values(array_unique(array_merge($realmRoles, $clientRoles)));

        return array_values(array_filter($all, fn($r) => ! in_array($r, $systemRoles, true)));
    }

    // Permissions
    public function getPermissions(string $accessToken): array
    {
        $cacheKey = 'kc_perms:' . hash('sha256', $accessToken);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($accessToken) {
            $introspection = $this->introspect($accessToken);
            return $this->extractPermissions($introspection);
        });
    }

    /**
     * Permissions diambil dari resource_access[client_id].roles di token.
     */
    public function extractPermissions(array $introspection): array
    {
        $clientRoles = $introspection['resource_access'][$this->clientId]['roles'] ?? [];

        $permissions = array_values(array_filter(
            $clientRoles,
            fn(string $r) => str_contains($r, ':')
        ));

        return array_values(array_unique($permissions));
    }

    // Admin API — Users 

    /**
     * Ambil daftar user dari Keycloak Admin API menggunakan client credentials.
     * Memerlukan service account dengan role view-users di realm.
     */
    public function getUsers(int $max = 200): array
    {
        $cacheKey = "kc_users:{$this->realm}";

        return Cache::remember($cacheKey, 30, function () use ($max) {
            $adminToken = $this->getAdminToken();

            $response = Http::timeout($this->timeout + 10)
                ->withToken($adminToken)
                ->acceptJson()
                ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users", [
                    'max'     => $max,
                    'enabled' => true,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    "Keycloak Admin API gagal: HTTP {$response->status()} — {$response->body()}"
                );
            }

            $users = $response->json() ?? [];

            // Ambil roles per user
            return collect($users)->map(function (array $u) {
                $roles = $this->getUserRoles($u['id'] ?? '');

                return [
                    'id'         => $u['id']                    ?? null,
                    'name'       => trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')) ?: ($u['username'] ?? '—'),
                    'username'   => $u['username']              ?? '—',
                    'email'      => $u['email']                 ?? '—',
                    'roles'      => $roles,
                    'role'       => $roles[0]                   ?? null,   
                    'enabled'    => $u['enabled']               ?? true,
                    'created_at' => isset($u['createdTimestamp'])
                        ? date('Y-m-d H:i:s', (int) ($u['createdTimestamp'] / 1000))
                        : null,
                    'login_type' => 'sso',
                ];
            })->values()->toArray();
        });
    }

    /**
     * Ambil client-level roles user dari Keycloak Admin API.
     */
    private function getUserRoles(string $userId): array
    {
        if (! $userId) return [];

        try {
            $adminToken = $this->getAdminToken();

            // Coba ambil client roles dulu (lebih spesifik)
            $clientsResp = Http::timeout($this->timeout)
                ->withToken($adminToken)
                ->acceptJson()
                ->get("{$this->baseUrl}/admin/realms/{$this->realm}/clients", [
                    'clientId' => $this->clientId,
                ]);

            $clientUuid = $clientsResp->json()[0]['id'] ?? null;

            $appRoles = [];
            if ($clientUuid) {
                $roleResp = Http::timeout($this->timeout)
                    ->withToken($adminToken)
                    ->acceptJson()
                    ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}/role-mappings/clients/{$clientUuid}");

                $appRoles = collect($roleResp->json() ?? [])
                    ->pluck('name')
                    ->toArray();
            }

            // Fallback ke realm roles jika tidak ada client roles
            if (empty($appRoles)) {
                $realmResp = Http::timeout($this->timeout)
                    ->withToken($adminToken)
                    ->acceptJson()
                    ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}/role-mappings/realm");

                $systemRoles = [
                    'uma_authorization', 'offline_access',
                    'default-roles-' . $this->realm,
                    'manage-account', 'manage-account-links', 'view-profile',
                ];

                $appRoles = collect($realmResp->json() ?? [])
                    ->pluck('name')
                    ->reject(fn($r) => in_array($r, $systemRoles, true))
                    ->values()
                    ->toArray();
            }

            return $appRoles;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Dapatkan admin token via client_credentials (service account).
     */
    private function getAdminToken(): string
    {
        $cacheKey = "kc_admin_token:{$this->realm}:{$this->clientId}";

        return Cache::remember($cacheKey, 55, function () {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post($this->tokenUrl(), [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            $token = $response->json()['access_token'] ?? null;

            if (! $token) {
                throw new \RuntimeException(
                    'Gagal mendapatkan admin token dari Keycloak: ' . $response->body()
                );
            }

            return $token;
        });
    }

    /**
     * Hapus cache daftar user (dipanggil setelah perubahan di Keycloak).
     */
    public function flushUsersCache(): void
    {
        Cache::forget("kc_users:{$this->realm}");
    }

    // Token ops
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post($this->tokenUrl(), [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
            ]);

        return $response->json() ?? [];
    }

    public function logout(string $refreshToken): void
    {
        Http::timeout($this->timeout)
            ->asForm()
            ->post($this->logoutUrl(), [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
            ]);
    }

    public function userinfo(string $accessToken): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withToken($accessToken)
                ->acceptJson()
                ->get($this->userinfoUrl());

            return $response->json() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
