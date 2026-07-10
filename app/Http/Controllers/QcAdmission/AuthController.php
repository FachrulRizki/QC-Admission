<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // login lokal 
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $field = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $credentials['username'], 'password' => $credentials['password']])) {
            return response()->json(['message' => 'Username atau password salah.'], 401);
        }

        $user = Auth::user();

        $request->session()->regenerate();
        $request->session()->put('auth_user', [
            'id'       => $user->id,
            'name'     => $user->name,
            'email'    => $user->email ?? '',
            'username' => $user->username,
            'roles'    => [$user->role],
        ]);

        ActivityLog::record('auth', 'login', "Login berhasil — {$user->name}");

        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $name = session('auth_user.name', 'Unknown');
        ActivityLog::record('auth', 'logout', "Logout — {$name}");

        $request->session()->flush();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => session('auth_user')]);
    }
}
