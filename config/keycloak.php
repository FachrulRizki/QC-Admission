<?php

// Konfigurasi Keycloak untuk KeycloakService (introspect, refresh, logout)
// OAuth2 redirect flow dihandle oleh config/services.php + Socialite

return [
    'base_url'     => env('KEYCLOAK_BASE_URL'),
    'realm'        => env('KEYCLOAK_REALM', 'master'),
    'client_id'    => env('KEYCLOAK_CLIENT_ID'),
    'client_secret'=> env('KEYCLOAK_CLIENT_SECRET'),
    'redirect_uri' => env('KEYCLOAK_REDIRECT_URI', env('APP_URL') . '/auth/keycloak/callback'),
    'cache_ttl'    => (int) env('KEYCLOAK_CACHE_TTL', 60),
    'timeout'      => (int) env('KEYCLOAK_TIMEOUT', 5),
];
