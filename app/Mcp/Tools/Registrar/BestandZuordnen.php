<?php

namespace App\Mcp\Tools\Registrar;

use App\Actions\Registrar\AssignInventory;
use App\Mcp\Tools\PortalTool;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('bestand-zuordnen')]
#[Description('Ordnet eine Domain oder ein Zertifikat einem Kunden zu — und optional der Kundenleistung, die sie abrechnet. Ohne „kunde_id" wird die Zuordnung aufgehoben; die Leistung fällt dann mit weg. Der Import überschreibt eine so gesetzte Zuordnung nicht.')]
class BestandZuordnen extends PortalTool
{
    public function __construct(private readonly AssignInventory $zuordnen) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'typ' => ['required', 'string', 'in:domain,zertifikat'],
            'id' => ['required', 'integer'],
            'kunde_id' => ['nullable', 'integer'],
            'leistung_id' => ['nullable', 'integer'],
        ]);

        $eintrag = $eingabe['typ'] === 'domain'
            ? Domain::query()->find($eingabe['id'])
            : Certificate::query()->find($eingabe['id']);

        if ($eintrag === null) {
            return Response::error($eingabe['typ'] === 'domain'
                ? 'Domain nicht gefunden.'
                : 'Zertifikat nicht gefunden.');
        }

        $kunde = null;

        if (filled($eingabe['kunde_id'] ?? null)) {
            $kunde = Customer::query()->find($eingabe['kunde_id']);

            if (! $kunde instanceof Customer) {
                return Response::error('Kunde nicht gefunden.');
            }
        }

        $leistung = null;

        if (filled($eingabe['leistung_id'] ?? null)) {
            $leistung = CustomerService::query()->find($eingabe['leistung_id']);

            if (! $leistung instanceof CustomerService) {
                return Response::error('Kundenleistung nicht gefunden.');
            }
        }

        try {
            ($this->zuordnen)($eintrag, $kunde, $leistung);
        } catch (InvalidArgumentException $ausnahme) {
            return Response::error($ausnahme->getMessage());
        }

        $eintrag->refresh()->load(['customer', 'customerService']);

        return Response::json([
            'typ' => $eingabe['typ'],
            'id' => $eintrag->id,
            'name' => $eintrag instanceof Domain ? $eintrag->name : $eintrag->common_name,
            'kunde' => $eintrag->customer === null ? null : [
                'id' => $eintrag->customer->id,
                'name' => $eintrag->customer->displayName(),
            ],
            'leistung' => $eintrag->customerService === null ? null : [
                'id' => $eintrag->customerService->id,
                'name' => $eintrag->customerService->billing_label ?: $eintrag->customerService->name,
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'typ' => $schema->string()->enum(['domain', 'zertifikat'])->description('Um welchen Bestand es geht.')->required(),
            'id' => $schema->integer()->description('Interne ID der Domain beziehungsweise des Zertifikats.')->required(),
            'kunde_id' => $schema->integer()->description('Weglassen oder leer lassen, um die Zuordnung aufzuheben.'),
            'leistung_id' => $schema->integer()
                ->description('Die Kundenleistung, die den Eintrag abrechnet. Muss demselben Kunden gehören; freiwillig, weil nicht jede Domain einzeln berechnet wird.'),
        ];
    }
}
