<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Root template yang diload saat pertama kali visit.
     */
    protected $rootView = 'app';

    /**
     * Data yang selalu tersedia di semua halaman Vue via usePage().props
     */
    public function share(Request $request): array
    {
        $user = session('auth_user');

        return array_merge(parent::share($request), [
            'auth' => [
                'user'  => $user ?? null,
                'roles' => $user['roles'] ?? [],
            ],
            'flash' => [
                'error'   => session('error'),
                'success' => session('success'),
            ],
            'ssoEnabled' => (bool) config('services.sso_enabled', false),
        ]);
    }
}
