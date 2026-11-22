<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LimitChatConcurrency
{
    public function handle(Request $request, Closure $next): Response
    {
        // Dos solicitudes de chat como máximo entre todos los workers.
        foreach ([1, 2] as $slot) {
            $lock = Cache::lock('chat:slot:'.$slot, 30);
            if ($lock->get()) {
                try {
                    return $next($request);
                } finally {
                    $lock->release();
                }
            }
        }

        return response()->json(
            ['message' => 'El asistente está ocupado. Inténtalo en unos segundos.'],
            503,
            ['Retry-After' => '5'],
        );
    }
}
