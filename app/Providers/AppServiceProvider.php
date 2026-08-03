<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Cara resmi socialiteproviders — via event listener, bukan extend manual
        Event::listen(SocialiteWasCalled::class, KeycloakExtendSocialite::class);

        // Di produksi dengan HTTPS, paksa semua URL yang digenerate Laravel pakai https://
        // Aktifkan dengan set APP_URL=https://... di .env
        if (str_starts_with(config('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
