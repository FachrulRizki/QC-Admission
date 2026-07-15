<?php

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => ['token' => env('POSTMARK_TOKEN')],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Keycloak SSO
    |--------------------------------------------------------------------------
    */
    'keycloak' => [
        'client_id'           => env('KEYCLOAK_CLIENT_ID'),
        'client_secret'       => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect'            => env('KEYCLOAK_REDIRECT_URI', env('APP_URL') . '/auth/keycloak/callback'),
        'base_url'            => env('KEYCLOAK_BASE_URL'),
        'realm'               => env('KEYCLOAK_REALM', 'master'),  // untuk KeycloakService
        'realms'              => env('KEYCLOAK_REALM', 'master'),  // untuk socialiteproviders/keycloak
        'cache_ttl'           => (int) env('KEYCLOAK_CACHE_TTL', 60),
        'timeout'             => (int) env('KEYCLOAK_TIMEOUT', 5),
        'role_permission_map' => env('KEYCLOAK_ROLE_PERMISSION_MAP', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bed Management IGD API
    |--------------------------------------------------------------------------
    |
    | auth_mode:
    |   'passthrough' → forward token Keycloak user yang sedang login (RECOMMENDED)
    |                   Tidak perlu credential terpisah. Token user dari session
    |                   dikirim langsung ke API Bed IGD untuk divalidasi mereka.
    |
    |   'keycloak'    → client_credentials grant menggunakan service account
    |                   aplikasi ini (KEYCLOAK_CLIENT_ID / KEYCLOAK_CLIENT_SECRET).
    |                   Cocok untuk proses background/cron tanpa user session.
    |
    |   'login'       → POST /auth/login ke API Bed IGD (username/password lokal).
    |
    |   'static'      → token tetap dari BED_IGD_STATIC_TOKEN di .env.
    |
    */
    'bed_igd' => [
        'base_url'            => env('BED_IGD_API_URL', ''),
        'enabled'             => env('BED_IGD_ENABLED', false),
        'update_path'         => env('BED_IGD_UPDATE_PATH', '/bed/release/trigger'),
        'update_mode'         => env('BED_IGD_UPDATE_MODE', 'direct'),

        // auth_mode: 'passthrough' | 'keycloak' | 'login' | 'static'
        'auth_mode'           => env('BED_IGD_AUTH_MODE', 'passthrough'),

        // Hanya dipakai saat auth_mode=login
        'username'            => env('BED_IGD_USERNAME', ''),
        'password'            => env('BED_IGD_PASSWORD', ''),
        'token_cache_minutes' => (int) env('BED_IGD_TOKEN_CACHE_MINUTES', 55),

        // Hanya dipakai saat auth_mode=static
        'static_token'        => env('BED_IGD_STATIC_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | KPI API
    |--------------------------------------------------------------------------
    */
    'kpi_api' => [
        'base_url'            => env('KPI_API_URL', 'https://kpi.urip.care/api'),
        'username'            => env('KPI_USERNAME', 'admin'),
        'password'            => env('KPI_PASSWORD', ''),
        'departemen'          => env('KPI_DEPARTEMEN', 'Customer Care'),
        'token_cache_minutes' => (int) env('KPI_TOKEN_CACHE_MINUTES', 55),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'sso_enabled'     => env('SSO_ENABLED', true),
    'rsus_db_enabled' => env('RSUS_DB_ENABLED', false),

];
