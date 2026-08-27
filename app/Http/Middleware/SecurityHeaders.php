<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow', false);

        $csp = implode('; ', [
            "default-src 'self'",
            // Alpine (TallStackUI) evaluiert x-data-Expressions zur Laufzeit per new Function().
            // Ohne 'unsafe-eval' initialisieren sämtliche Komponenten nicht — der Dialog
            // erscheint dann als leeres, unbedienbares Overlay über jeder Seite.
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp, false);

        return $response;
    }
}
