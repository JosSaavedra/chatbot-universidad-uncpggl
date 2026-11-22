<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoStoreApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // El código OAuth viaja en la URL de retorno: impedir que se filtre como Referer.
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if ($request->is('api/*', 'auth/google/*', 'acceso/qr/*', 'acceso/correo/*', 'sanctum/csrf-cookie')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        return $response;
    }
}
