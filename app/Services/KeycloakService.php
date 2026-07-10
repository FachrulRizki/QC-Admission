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
    private int $timeout;
    private int $cacheTtl;

    public function __construct()
    {
        $this->baseUrl      = config('services.keycloak.base_url', '');
        $this->realm        = config('services.keycloak.realm', 'master');
        $this->clientId     = config('services.keycloak.client_id', '');
        $this->clientSecret = config('services.keycloak.client_secret', '');
        $this->timeout      = (int) config('keycloak.timeout', 5);
        $this->cacheTtl     = (int) config('keycloak.cache_ttl', 60);
    }

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

    /**
     * Introspect access token ke Keycloak dengan caching.
     */
    public function introspect(string $accessToken): array
    {
        $cacheKey = 'keycloak_token:' . hash('sha256', $accessToken);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($accessToken) {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post($this->introspectUrl(), [
                    'token' => $accessToken,
                ]);

            return $response->json() ?? [];
        });
    }

    /**
     * Hapus cache introspection untuk token tertentu (dipakai saat logout).
     */
    public function forgetTokenCache(string $accessToken): void
    {
        Cache::forget('keycloak_token:' . hash('sha256', $accessToken));
    }

    /**
     * Refresh access token menggunakan refresh token.
     */
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

    /**
     * Back-channel logout — invalidate session di Keycloak.
     */
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

    /**
     * Ambil roles dari hasil introspection.
     */
    public function getRoles(array $introspection): array
    {
        $realmRoles  = $introspection['realm_access']['roles'] ?? [];
        $clientRoles = $introspection['resource_access'][$this->clientId]['roles'] ?? [];

        return array_values(array_unique(array_merge($realmRoles, $clientRoles)));
    }
}
