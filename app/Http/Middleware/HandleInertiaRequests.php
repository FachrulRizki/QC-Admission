<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = session('auth_user');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user
                    ? [
                        'id'         => $user['id'],
                        'name'       => $user['name'],
                        'email'      => $user['email']      ?? '',
                        'username'   => $user['username']   ?? '',
                        'login_type' => $user['login_type'] ?? 'sso',
                    ]
                    : null,
                'roles' => $user['roles']       ?? [],
                'permissions' => $user['permissions'] ?? [],
            ],
            'flash' => [
                'error'   => session('error'),
                'success' => session('success'),
            ],
            'ssoEnabled'      => true,
        ]);
    }
}
