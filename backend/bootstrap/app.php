<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\LimitChatConcurrency;
use App\Http\Middleware\NoStoreApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En Render el tráfico llega a través de un proxy inverso. La lista de
        // proxies es configurable para que el limitador por IP use la dirección
        // real del cliente y no la dirección interna compartida.
        $trustedProxies = env('TRUSTED_PROXIES');
        if ($trustedProxies === '*' && ! env('RENDER', false)) {
            $trustedProxies = null;
        }
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->prepend(NoStoreApi::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'chat.capacity' => LimitChatConcurrency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
