<?php

use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackUserSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            if (! config('portal.customer_portal.enabled')) {
                return;
            }

            $kundenportal = Route::middleware('web')->name('customer-portal.');

            // Eigene Domain oder Pfad unterhalb der Anwendung.
            if (filled(config('portal.customer_portal.domain'))) {
                $kundenportal->domain(config('portal.customer_portal.domain'));
            } else {
                $kundenportal->prefix(config('portal.customer_portal.path'));
            }

            $kundenportal->group(base_path('routes/customer-portal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            TrackUserSession::class,
            SecurityHeaders::class,
            EnsureTwoFactorIsEnabled::class,
        ]);

        // Kunden und Team haben getrennte Anmeldeseiten.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->routeIs('customer-portal.*')
            ? route('customer-portal.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request): string => $request->routeIs('customer-portal.*')
            ? route('customer-portal.dashboard')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
