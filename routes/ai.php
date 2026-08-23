<?php

use App\Mcp\Servers\PortalServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP-Server
|--------------------------------------------------------------------------
|
| Der Portal-Server stellt Kunden, Ansprechpartner, Katalog, Leistungen und
| Preise als Werkzeuge bereit. Der Zugang laeuft ueber persoenliche Tokens:
| jeder Aufruf handelt im Namen des Benutzers, dem das Token gehoert, und
| erscheint unter dessen Namen in der Aenderungshistorie.
|
| Tokens werden mit `php artisan portal:mcp-token` ausgestellt und widerrufen.
|
*/

if (config('portal.mcp.enabled')) {
    Mcp::web(config('portal.mcp.path'), PortalServer::class)
        ->middleware(['auth:sanctum', 'throttle:'.config('portal.mcp.rate_limit')])
        ->name('mcp.portal');
}
