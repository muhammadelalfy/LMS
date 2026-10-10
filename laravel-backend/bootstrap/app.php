<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\IdempotentRequests;
use Modules\Tenancy\Http\Middleware\EnsureSchoolIsActive;
use Modules\Tenancy\Http\Middleware\InitializeSchool;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // The app signs in to private channels with its Sanctum token:
    // POST /api/broadcasting/auth.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'school', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
        // Everything a school's people use: find the school from the address
        // (never on a central domain), and turn away a locked school.
        $middleware->group('school', [
            InitializeSchool::class,
            EnsureSchoolIsActive::class,
            // Last, so the school's cache prefix is already in place: a write
            // sent again with the same Idempotency-Key is answered, not repeated.
            IdempotentRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
