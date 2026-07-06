<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Keycloak SSO
    |--------------------------------------------------------------------------
    | Configure your Keycloak realm to enable SSO login.
    | The base_url should NOT include /realms/... — just the root Keycloak URL.
    | e.g. https://sso.rsud.local:8080
    */
    'keycloak' => [
        'base_url'      => env('KEYCLOAK_BASE_URL', ''),
        'realm'         => env('KEYCLOAK_REALM', 'master'),
        'client_id'     => env('KEYCLOAK_CLIENT_ID', 'qc-admission'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bed Management IGD API
    |--------------------------------------------------------------------------
    | Autentikasi menggunakan username/password → ambil token → cache 55 menit.
    | Endpoint yang digunakan:
    |   GET  {base_url}/beds?no_reg=xxx    — daftar bed berdasarkan No. Reg
    |   POST {base_url}/beds/update-status — update status bed (KOSONG/TERISI)
    |
    | bed_igd_enabled: true  = pakai API (butuh url + credential terisi)
    |                  false = fallback ke RSUS DB langsung, atau mock jika RSUS juga off
    */
    'bed_igd' => [
        'base_url'             => env('BED_IGD_API_URL', ''),
        'username'             => env('BED_IGD_USERNAME', ''),
        'password'             => env('BED_IGD_PASSWORD', ''),
        'token_cache_minutes'  => 55,
        'enabled'              => env('BED_IGD_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | KPI API — Pegawai Customer Care
    |--------------------------------------------------------------------------
    | Digunakan untuk mengambil daftar petugas dari departemen Customer Care.
    | Autentikasi menggunakan username/password → ambil token → fetch pegawai.
    | Token di-cache selama 55 menit (masa berlaku token biasanya 1 jam).
    */
    'kpi_api' => [
        'base_url'    => env('KPI_API_URL', 'https://kpi.urip.care/api'),
        'username'    => env('KPI_USERNAME', 'admin'),
        'password'    => env('KPI_PASSWORD', 'password123'),
        'departemen'  => env('KPI_DEPARTEMEN', 'Customer Care'),
        'token_cache_minutes' => 55,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags — Multi-Tenant
    |--------------------------------------------------------------------------
    | SSO_ENABLED    : true  = login via Keycloak SSO aktif (jaringan RS)
    |                  false = login lokal saja
    | RSUS_DB_ENABLED: true  = query pasien langsung ke DB SQL Server RSUS
    |                  false = fallback mock / API eksternal
    */
    'sso_enabled'     => env('SSO_ENABLED', false),
    'rsus_db_enabled' => env('RSUS_DB_ENABLED', false),

];
