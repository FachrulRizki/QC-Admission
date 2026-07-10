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

    // Keycloak SSO — Socialite + token introspection
    // auth_server_url & base_url sama-sama baca KEYCLOAK_BASE_URL (beda key untuk Socialite vs KeycloakService)
    'keycloak' => [
        'client_id'       => env('KEYCLOAK_CLIENT_ID'),
        'client_secret'   => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect'        => env('KEYCLOAK_REDIRECT_URI', env('APP_URL') . '/auth/keycloak/callback'),
        'auth_server_url' => env('KEYCLOAK_BASE_URL'),
        'base_url'        => env('KEYCLOAK_BASE_URL'),
        'realm'           => env('KEYCLOAK_REALM', 'master'),
    ],

    // Bed Management IGD API (opsional, fallback ke RSUS DB atau mock)
    'bed_igd' => [
        'base_url'            => env('BED_IGD_API_URL', ''),
        'username'            => env('BED_IGD_USERNAME', ''),
        'password'            => env('BED_IGD_PASSWORD', ''),
        'token_cache_minutes' => 55,
        'enabled'             => env('BED_IGD_ENABLED', false),
    ],

    // KPI API — sumber daftar petugas Customer Care
    'kpi_api' => [
        'base_url'            => env('KPI_API_URL', 'https://kpi.urip.care/api'),
        'username'            => env('KPI_USERNAME', 'admin'),
        'password'            => env('KPI_PASSWORD', ''),
        'departemen'          => env('KPI_DEPARTEMEN', 'Customer Care'),
        'token_cache_minutes' => 55,
    ],

    // Feature flags
    'sso_enabled'     => env('SSO_ENABLED', false),
    'rsus_db_enabled' => env('RSUS_DB_ENABLED', false),

];
