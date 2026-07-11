<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * KeycloakService — sumber kebenaran auth SSO.
 *
 * Roles dan permissions berasal sepenuhnya dari Keycloak.
 * Tidak ada hardcode role di app — semua dikonfigurasi di Keycloak Admin Console.
 */
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

    // ── URL helpers ───────────────────────────────────────────────────────────

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

    // ── Introspect ────────────────────────────────────────────────────────────

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

    // ── Roles ─────────────────────────────────────────────────────────────────

    /**
     * Ambil roles dari introspect payload.
     *
     * Sumber roles (urutan prioritas):
     *  1. resource_access[client_id].roles  → role spesifik client qc-admission
     *  2. realm_access.roles                → role realm-level (jika ada)
     *
     * Role di-assign di Keycloak Admin:
     *  Clients → qc-admission → Roles → buat: admin, qc_admission, kasir
     *  Users → [user] → Role mapping → assign dari client qc-admission
     */
    public function getRoles(array $introspection): array
    {
        // System roles Keycloak yang tidak relevan untuk app
        $systemRoles = [
            'uma_authorization',
            'offline_access',
            'default-roles-' . $this->realm,
            'manage-account',
            'manage-account-links',
            'view-profile',
        ];

        // Roles dari client qc-admission
        $clientRoles = $introspection['resource_access'][$this->clientId]['roles'] ?? [];

        // Realm roles (biasanya untuk role yang berlaku di semua client)
        $realmRoles = $introspection['realm_access']['roles'] ?? [];

        $all = array_values(array_unique(array_merge($realmRoles, $clientRoles)));

        return array_values(array_filter($all, fn($r) => ! in_array($r, $systemRoles, true)));
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    /**
     * Derive permissions dari roles.
     * Mapping dikontrol via env KEYCLOAK_ROLE_PERMISSION_MAP (JSON),
     * atau gunakan default map di bawah.
     */
    public function getPermissions(string $accessToken): array
    {
        $cacheKey = 'kc_perms:' . hash('sha256', $accessToken);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($accessToken) {
            $introspection = $this->introspect($accessToken);
            $roles         = $this->getRoles($introspection);
            return $this->derivePermissionsFromRoles($roles);
        });
    }

    private function derivePermissionsFromRoles(array $roles): array
    {
        $mapJson = config('services.keycloak.role_permission_map', null);

        $map = $mapJson
            ? (json_decode($mapJson, true) ?? [])
            : [
                'admin' => [
                    'dashboard:view',
                    'quality-control:view', 'quality-control:write', 'quality-control:delete',
                    'edukasi-lanjutan:view', 'edukasi-lanjutan:write', 'edukasi-lanjutan:delete',
                    'batal-ranap:view', 'batal-ranap:write', 'batal-ranap:delete', 'batal-ranap:closing',
                    'up-selling:view', 'up-selling:write', 'up-selling:delete',
                    'master-data:view', 'master-data:write', 'master-data:delete',
                    'activity-log:view',
                    'user-management:view', 'user-management:write', 'user-management:delete',
                    'bed-management:view', 'bed-management:write',
                    'pegawai:view', 'pasien:view',
                ],
                'qc_admission' => [
                    'dashboard:view',
                    'quality-control:view', 'quality-control:write',
                    'edukasi-lanjutan:view', 'edukasi-lanjutan:write',
                    'batal-ranap:view', 'batal-ranap:write', 'batal-ranap:closing',
                    'up-selling:view', 'up-selling:write',
                    'master-data:view',
                    'bed-management:view', 'bed-management:write',
                    'pegawai:view', 'pasien:view',
                ],
                'kasir' => [
                    'batal-ranap:view',
                    'master-data:view',
                    'pasien:view',
                ],
            ];

        $perms = [];
        foreach ($roles as $role) {
            if (isset($map[$role])) {
                $perms = array_merge($perms, $map[$role]);
            }
        }

        return array_values(array_unique($perms));
    }

    // ── Token ops ─────────────────────────────────────────────────────────────

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
