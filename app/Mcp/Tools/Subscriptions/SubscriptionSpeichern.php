<?php

namespace App\Mcp\Tools\Subscriptions;

use App\Actions\Subscriptions\SaveSubscription;
use App\Http\Resources\SubscriptionResource;
use App\Mcp\Tools\PortalTool;
use App\Models\Subscription;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('subscription-speichern')]
#[Description('Legt einen manuell gepflegten Plesk-Webspace an oder ändert ihn anhand der internen ID. Ohne ID sind server und name erforderlich. Ausgelassene Felder bleiben erhalten, null leert optionale Felder. cloud01/cloud02/cloud03 sind reine Kennungen: keine Verbindung zu Plesk, kein Import. Abrechnung über customer_service_id.')]
class SubscriptionSpeichern extends PortalTool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate(['id' => ['sometimes', 'integer', 'min:1', 'exists:subscriptions,id']]);
        $subscription = isset($input['id']) ? Subscription::query()->findOrFail($input['id']) : null;
        $saved = app(SaveSubscription::class)($request->all(), $subscription)->load(['customer', 'customerService']);

        return Response::json((new SubscriptionResource($saved))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->description('Interne ID zum Ändern; ohne ID neu anlegen.'), 'server' => $schema->string()->enum(['cloud01', 'cloud02', 'cloud03']), 'name' => $schema->string()->description('Hauptdomain.'), 'plesk_id' => $schema->integer()->nullable(), 'status' => $schema->string()->enum(['active', 'suspended', 'disabled', 'archived']), 'hosting_login' => $schema->string()->nullable(), 'service_plan' => $schema->string()->nullable(), 'subscriber' => $schema->string()->nullable(), 'setup_date' => $schema->string()->nullable()->description('JJJJ-MM-TT.'), 'ip_address' => $schema->string()->nullable(), 'expires_on' => $schema->string()->nullable()->description('JJJJ-MM-TT.'), 'description' => $schema->string()->nullable(), 'customer_id' => $schema->integer()->nullable(), 'customer_service_id' => $schema->integer()->nullable()];
    }
}
