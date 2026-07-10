<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckKeycloakRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $userRoles = session('auth_user.roles', []);

        // Flatten comma-separated dalam satu argumen
        $required = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $r) {
                $required[] = trim($r);
            }
        }

        foreach ($required as $role) {
            if (in_array($role, $userRoles, true)) {
                return $next($request);
            }
        }

        if ($request->inertia()) {
            return redirect()->route('akses.ditolak');
        }

        return response()->json(['message' => 'Akses ditolak — role tidak memadai.'], 403);
    }
}
