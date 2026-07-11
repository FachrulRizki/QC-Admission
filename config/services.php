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
    */
    'bed_igd' => [
        'base_url'            => env('BED_IGD_API_URL', ''),
        'username'            => env('BED_IGD_USERNAME', ''),
        'password'            => env('BED_IGD_PASSWORD', ''),
        'token_cache_minutes' => (int) env('BED_IGD_TOKEN_CACHE_MINUTES', 55),
        'enabled'             => env('BED_IGD_ENABLED', false),
        'update_mode'         => env('BED_IGD_UPDATE_MODE', 'direct'),
        'update_path'         => env('BED_IGD_UPDATE_PATH', '/bed/release/trigger'),
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
