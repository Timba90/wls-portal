<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\View\View;
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
            $this->formAction($response),
        ]);
        $response->headers->set('Content-Security-Policy', $csp, false);

        return $response;
    }

    private function formAction(Response $response): string
    {
        $policy = "form-action 'self'";

        if (! $response instanceof LaravelResponse || ! $response->isSuccessful()) {
            return $policy;
        }

        $view = $response->getOriginalContent();

        if (! $view instanceof View || $view->name() !== 'oauth.authorize') {
            return $policy;
        }

        // Passport rendert diese View erst nach der Pruefung der Rueckleitungsadresse.
        // Chromium wendet form-action auch auf die Weiterleitung nach dem POST an.
        $uri = $view->getData()['request']->query('redirect_uri');
        $parts = is_string($uri) ? parse_url($uri) : false;

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true)
            || ! preg_match('/\A[a-zA-Z0-9.\[\]:-]+\z/', $parts['host'] ?? '')) {
            return $policy;
        }

        return $policy.' '.$parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
