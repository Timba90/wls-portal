<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verlangt von einem OAuth-Zugriff den Geltungsbereich des MCP-Servers.
 *
 * Nur fuer OAuth: ein persoenliches Token aus `portal:mcp-token` traegt die
 * vollen Rechte seines Benutzers und kennt keine Geltungsbereiche. Es hier
 * abzuweisen wuerde den zweiten Weg schliessen, den es weiter geben soll.
 *
 * Ein OAuth-Token ohne `mcp:use` bedeutet dagegen, dass der Benutzer genau
 * diesem Zugriff nicht zugestimmt hat.
 */
class EnsureMcpScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof AccessToken && ! $token->can(Registrar::OAUTH_SCOPE)) {
            return response()->json([
                'error' => 'insufficient_scope',
                'error_description' => sprintf(
                    'Dieser Zugriff hat den Geltungsbereich %s nicht.',
                    Registrar::OAUTH_SCOPE,
                ),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
