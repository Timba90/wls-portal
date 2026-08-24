<?php

namespace App\Mcp\Tools\Services;

use App\Actions\Services\CompareWithCatalog;
use App\Actions\Services\ResolveCatalogChange;
use App\Exceptions\ReadOnlyRecordException;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('katalogaenderung-entscheiden')]
#[Description('Entscheidet eine Katalogänderung: „uebernehmen" setzt den heutigen Katalogwert auf die Leistung, „behalten" lässt sie unverändert. Beides schließt den Vorgang ab. Übernommene Preise laufen über den Preisverlauf. Ohne „feld" gilt die Entscheidung für alle offenen Änderungen.')]
class KatalogaenderungEntscheiden extends PortalTool
{
    public function __construct(
        private readonly CompareWithCatalog $compareWithCatalog,
        private readonly ResolveCatalogChange $resolveCatalogChange,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'entscheidung' => ['required', 'string', 'in:uebernehmen,behalten'],
            'feld' => ['nullable', 'string', 'in:sales_price_cents,purchase_price_cents,billing_interval,category'],
        ]);

        $leistung = CustomerService::query()->with(['product', 'productVariant'])->find($eingabe['id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        $uebernehmen = $eingabe['entscheidung'] === 'uebernehmen';

        $felder = filled($eingabe['feld'] ?? null)
            ? [$eingabe['feld']]
            : collect(($this->compareWithCatalog)($leistung))
                ->filter(fn (array $zeile): bool => $zeile['katalogGeaendert'] && $zeile['uebernehmbar'])
                ->pluck('feld')
                ->all();

        if ($felder === []) {
            return Response::json(['vorgang' => 'nichts zu entscheiden', 'id' => $leistung->id]);
        }

        try {
            $this->resolveCatalogChange->all($leistung, $felder, $uebernehmen, $request->user());
        } catch (ReadOnlyRecordException|InvalidArgumentException $ausnahme) {
            return Response::error($ausnahme->getMessage());
        }

        return Response::json([
            'vorgang' => $uebernehmen ? 'übernommen' : 'behalten',
            'id' => $leistung->id,
            'felder' => $felder,
            'verkaufspreis' => $this->money($leistung->refresh()->sales_price_cents),
            'einkaufspreis' => $this->money($leistung->purchase_price_cents),
            'offen' => $this->compareWithCatalog->hasOpenChanges($leistung),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Kundenleistung.')->required(),
            'entscheidung' => $schema->string()->description('uebernehmen oder behalten.')->required(),
            'feld' => $schema->string()->description('sales_price_cents, purchase_price_cents, billing_interval oder category. Ohne Angabe alle offenen Änderungen.'),
        ];
    }
}
