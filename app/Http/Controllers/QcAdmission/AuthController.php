<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = session('auth_user');

        if (! $user) {
            return response()->json(['user' => null, 'authenticated' => false]);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'          => $user['id']          ?? null,
                'name'        => $user['name']        ?? null,
                'email'       => $user['email']       ?? null,
                'username'    => $user['username']    ?? null,
                'roles'       => $user['roles']       ?? [],
                'permissions' => $user['permissions'] ?? [],
                'login_type'  => $user['login_type']  ?? 'sso',
            ],
        ]);
    }
}
