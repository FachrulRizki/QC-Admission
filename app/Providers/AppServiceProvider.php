<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Daftarkan Keycloak driver ke Socialite
        $socialite = $this->app->make(Factory::class);
        $socialite->extend('keycloak', function () use ($socialite) {
            $config = config('services.keycloak');
            return $socialite->buildProvider(
                \SocialiteProviders\Keycloak\Provider::class,
                $config
            );
        });
    }
}
