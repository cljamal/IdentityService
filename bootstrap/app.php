<?php

use App\Http\Middleware\AuthenticateClient;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
    // Default attributes put /broadcasting/auth behind session/CSRF ('web'
    // middleware) — this API is stateless JWT only, so it needs to sit
    // under /api and authenticate via our own "id-api" guard instead.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:id-api']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'client' => AuthenticateClient::class,
        ]);

        // Without this, SortedMiddleware (using the framework's default
        // priority list — this app never overrides it) always moves
        // Authenticate ("auth:id-api") ahead of any middleware not in that
        // list, "client" included, on every route that combines both (me,
        // sessions, logout, password/change, ...). IdApiGuard::user() would
        // then run before AuthenticateClient ever populates CurrentClient,
        // so its cross-client check silently no-ops and caches the user —
        // an access token minted under Client A authenticates through
        // Client B's credentials. This forces "client" to run first.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: AuthenticateClient::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
