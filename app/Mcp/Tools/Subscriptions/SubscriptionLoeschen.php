<?php

namespace App\Mcp\Tools\Subscriptions;

use App\Actions\Subscriptions\DeleteSubscriptions;
use App\Mcp\Tools\PortalTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('subscription-loeschen')]
#[Description('Löscht ausschließlich Portal-Subscriptions anhand expliziter interner IDs (1 bis 100). Endgültig, mit Änderungshistorie. Kunden, Domains, Leistungen und echte Plesk-Webspaces bleiben erhalten. Bei ungültiger Auswahl wird nichts gelöscht.')]
#[IsDestructive]
class SubscriptionLoeschen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100']]);

        return Response::json(['deleted' => app(DeleteSubscriptions::class)($input['ids'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['ids' => $schema->array()->items($schema->integer())->min(1)->max(100)->required()->description('Interne Portal-IDs der zu löschenden Subscriptions.')];
    }
}
