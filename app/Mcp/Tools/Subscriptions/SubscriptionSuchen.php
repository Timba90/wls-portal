<?php

namespace App\Mcp\Tools\Subscriptions;

use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use App\Http\Resources\SubscriptionResource;
use App\Mcp\Tools\PortalTool;
use App\Models\Subscription;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('subscription-suchen')]
#[Description('Durchsucht manuell erfasste Plesk-Webspaces nach Hauptdomain, Server, Status und Kunde. Mit Seite und Anzahl blättern. Kein Live-Abgleich mit Plesk.')]
#[IsReadOnly]
class SubscriptionSuchen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate(['search' => ['nullable', 'string', 'max:253'], 'server' => ['nullable', Rule::enum(PleskServer::class)], 'status' => ['nullable', Rule::enum(SubscriptionStatus::class)], 'customer_id' => ['nullable', 'integer', 'exists:customers,id'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = Subscription::query()->with(['customer', 'customerService']);
        if (filled($input['search'] ?? null)) {
            $query->searchName($input['search']);
        }
        foreach (['server', 'status', 'customer_id'] as $field) {
            if (filled($input[$field] ?? null)) {
                $query->where($field, $input[$field]);
            }
        }
        $results = $query->orderBy('name')->orderBy('id')->paginate($input['per_page'] ?? 25, ['*'], 'page', $input['page'] ?? 1);

        return Response::json(['data' => SubscriptionResource::collection($results->getCollection())->resolve(), 'current_page' => $results->currentPage(), 'last_page' => $results->lastPage(), 'total' => $results->total()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['search' => $schema->string()->description('Teil der Hauptdomain.'), 'server' => $schema->string()->enum(['cloud01', 'cloud02', 'cloud03']), 'status' => $schema->string()->enum(['active', 'suspended', 'disabled', 'archived']), 'customer_id' => $schema->integer(), 'page' => $schema->integer(), 'per_page' => $schema->integer()->description('Standard 25, höchstens 100.')];
    }
}
