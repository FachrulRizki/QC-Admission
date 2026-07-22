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
        $realmRoles  = $introspection['realm_access']['roles'] ?? [];

        $all = array_values(array_unique(array_merge($realmRoles, $clientRoles)));

        // Ambil semua role yang bukan system role dan bukan permission format (module:action)
        $appRoles = array_values(array_filter(
            $all,
            fn($r) => ! in_array($r, $systemRoles, true) && ! str_contains($r, ':')
        ));

        // Fallback: jika tidak ada role eksplisit, derive dari permissions yang ada
        if (empty($appRoles)) {
            $permissions = array_values(array_filter($clientRoles, fn($r) => str_contains($r, ':')));
            $appRoles = $this->deriveRoleFromPermissions($permissions);
        }

        return $appRoles;
    }

    /**
     * Derive role aplikasi dari daftar permissions.
     * Gunakan sebagai fallback jika role eksplisit tidak di-set di Keycloak.
     */
    private function deriveRoleFromPermissions(array $permissions): array
    {
        if (empty($permissions)) {
            return [];
        }

        // Jika punya banyak modul write → qc_admission atau admin
        $writePerms = array_filter($permissions, fn($p) => str_ends_with($p, ':write'));
        if (count($writePerms) >= 4) {
            return ['admin'];
        }
        if (count($writePerms) >= 2) {
            return ['qc_admission'];
        }

        // Jika hanya view/minimal → kasir
        return ['kasir'];
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
