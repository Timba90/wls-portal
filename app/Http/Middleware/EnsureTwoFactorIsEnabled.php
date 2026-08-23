<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt Zwei-Faktor-Authentifizierung, sofern sie global vorgeschrieben ist.
 *
 * Standardmäßig ist 2FA optional (`auth.two_factor_required` = false). Wird der
 * Wert auf true gesetzt, landen Benutzer ohne bestätigtes 2FA auf der
 * Sicherheitsseite, bis sie es eingerichtet haben.
 */
class EnsureTwoFactorIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.two_factor_required')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ! $user->hasTwoFactorEnabled() && ! $request->routeIs('profile.security')) {
            return redirect()
                ->route('profile.security')
                ->with('warning', 'Zwei-Faktor-Authentifizierung ist verpflichtend. Bitte richten Sie sie jetzt ein.');
        }

        return $next($request);
    }
}
