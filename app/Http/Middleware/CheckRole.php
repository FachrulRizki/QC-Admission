<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Periksa apakah user punya salah satu dari role yang diizinkan.
     * Contoh route: Route::middleware('role:admin,qc_admission')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Akses ditolak. Role Anda tidak memiliki izin untuk aksi ini.',
            ], 403);
        }

        return $next($request);
    }
}
