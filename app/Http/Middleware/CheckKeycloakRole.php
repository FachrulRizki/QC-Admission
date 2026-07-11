<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckKeycloakRole
{
    public function handle(Request $request, Closure $next, string ...$required)
    {
        $authUser    = session('auth_user', []);
        $userRoles   = $authUser['roles']       ?? [];
        $userPerms   = $authUser['permissions'] ?? [];

        $checks = [];
        foreach ($required as $item) {
            foreach (explode(',', $item) as $r) {
                $checks[] = trim($r);
            }
        }

        foreach ($checks as $check) {
            if (str_contains($check, ':')) {
                if (in_array($check, $userPerms, true)) {
                    return $next($request);
                }
            } else {
                if (in_array($check, $userRoles, true)) {
                    return $next($request);
                }
            }
        }

        // Akses ditolak
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Akses ditolak. Role atau permission tidak memadai.',
                'required' => $checks,
            ], 403);
        }

        return redirect()->route('akses.ditolak');
    }
}
