<?php

namespace App\Mcp\Tools\Subscriptions;

use App\Http\Resources\SubscriptionResource;
use App\Mcp\Tools\PortalTool;
use App\Models\Subscription;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('subscription-lesen')]
#[Description('Liest einen manuell gepflegten Plesk-Webspace mit Kunde und verknüpfter Abrechnungseinstellung. Kein Abruf beim Server und kein Rechnungsnachweis.')]
#[IsReadOnly]
class SubscriptionLesen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate(['id' => ['required', 'integer', 'min:1', 'exists:subscriptions,id']]);

        return Response::json((new SubscriptionResource(Subscription::query()->with(['customer', 'customerService'])->findOrFail($input['id'])))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()->description('Interne Subscription-ID.')];
    }
}
