<?php

use App\Exceptions\ConcurrentModificationException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            AssignRequestId::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            EnsureUserIsNotBlocked::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        if ($trustedProxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $trustedProxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ConcurrentModificationException $e, Request $request) {
            Log::warning('Concurrent modification conflict', [
                'url' => $request->fullUrl(),
                'user_id' => $request->user()?->id,
            ]);

            $message = __('errors.409.message');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'code' => 'CONCURRENT_MODIFICATION',
                ], 409);
            }

            return response()->view('errors.409', [
                'message' => $message,
            ], 409);
        });
    })->create();
