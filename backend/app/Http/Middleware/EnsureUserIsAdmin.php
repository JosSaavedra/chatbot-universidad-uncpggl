<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Permite el acceso únicamente a usuarios administradores autenticados.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || empty($user->is_admin)) {
            return response()->json([
                'message' => 'No autorizado. Solo un administrador puede realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
