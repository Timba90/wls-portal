<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schreibt Metadaten der aktuellen Sitzung fort, damit die Sessionverwaltung
 * die aktiven Sitzungen eines Benutzers anzeigen kann.
 *
 * Notwendig, weil der Redis-Session-Treiber keine Auflistung erlaubt.
 */
class TrackUserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || ! $request->hasSession()) {
            return $response;
        }

        UserSession::query()->updateOrCreate(
            ['id' => $request->session()->getId()],
            [
                'user_id' => $request->user()->getAuthIdentifier(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'last_activity' => time(),
            ],
        );

        return $response;
    }
}
