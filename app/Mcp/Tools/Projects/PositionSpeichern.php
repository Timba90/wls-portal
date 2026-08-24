<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\DeletePosition;
use App\Actions\Projects\SavePosition;
use App\Exceptions\ReadOnlyRecordException;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectPosition;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('position-speichern')]
#[Description('Legt eine Projektposition an, ändert sie oder entfernt sie. Wird „produkt_id" oder „kundenleistung_id" angegeben, werden Name, Preis und Art von dort als Vorschlag übernommen und können überschrieben werden — ein Projekt darf vom Listenpreis abweichen. Nur einmalige Positionen bilden das Projektvolumen.')]
class PositionSpeichern extends PortalTool
{
    public function __construct(
        private readonly SavePosition $savePosition,
        private readonly DeletePosition $deletePosition,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'projekt_id' => ['required', 'integer'],
            'id' => ['nullable', 'integer'],
            'produkt_id' => ['nullable', 'integer', 'exists:products,id'],
            'kundenleistung_id' => ['nullable', 'integer', 'exists:customer_services,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'art' => ['nullable', 'string', 'in:one_time,recurring'],
            'menge' => ['nullable', 'numeric', 'min:0'],
            'einzelpreis_cents' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:planned,active,paused,ended'],
            'sortierung' => ['nullable', 'integer', 'min:0'],
            'entfernen' => ['nullable', 'boolean'],
        ]);

        $projekt = Project::query()->find($eingabe['projekt_id']);

        if (! $projekt instanceof Project) {
            return Response::error('Projekt nicht gefunden.');
        }

        $position = filled($eingabe['id'] ?? null)
            ? $projekt->positions()->find($eingabe['id'])
            : null;

        if (filled($eingabe['id'] ?? null) && ! $position instanceof ProjectPosition) {
            return Response::error('Position gehört nicht zu diesem Projekt.');
        }

        try {
            if ($eingabe['entfernen'] ?? false) {
                if (! $position instanceof ProjectPosition) {
                    return Response::error('Zum Entfernen wird „id" benötigt.');
                }

                ($this->deletePosition)($position);

                return $this->antwort($projekt->fresh('positions'), 'entfernt', null);
            }

            $vorschlag = $this->vorschlag($projekt, $eingabe);

            if ($vorschlag instanceof Response) {
                return $vorschlag;
            }

            $name = $eingabe['name'] ?? $vorschlag['name'] ?? $position?->name;

            if (blank($name)) {
                return Response::error('Zum Anlegen wird „name" oder eine Herkunft benötigt.');
            }

            $preis = $eingabe['einzelpreis_cents']
                ?? ($vorschlag === [] ? null : Money::fromEuroInput($vorschlag['unit_price'])->cents)
                ?? $position?->unit_price_cents
                ?? 0;

            $gespeichert = ($this->savePosition)($projekt, [
                'product_id' => $eingabe['produkt_id'] ?? $position?->product_id,
                'customer_service_id' => $eingabe['kundenleistung_id'] ?? $position?->customer_service_id,
                'name' => $name,
                'kind' => $eingabe['art'] ?? $vorschlag['kind'] ?? $position?->kind->value ?? 'one_time',
                'quantity' => $eingabe['menge'] ?? (float) ($position?->quantity ?? 1),
                'unit_price' => Money::fromCents((int) $preis)->toInput(),
                'status' => $eingabe['status'] ?? $position?->status->value ?? 'active',
                'sort_order' => $eingabe['sortierung'] ?? $position?->sort_order,
            ], $position);
        } catch (ReadOnlyRecordException $ausnahme) {
            return Response::error($ausnahme->getMessage());
        }

        return $this->antwort($projekt->fresh('positions'), $position ? 'geändert' : 'angelegt', $gespeichert);
    }

    /**
     * Vorschlagswerte aus Katalogartikel oder Kundenleistung.
     *
     * @param  array<string, mixed>  $eingabe
     * @return array{name?: string, unit_price?: string, kind?: string}|Response
     */
    private function vorschlag(Project $projekt, array $eingabe): array|Response
    {
        if (filled($eingabe['kundenleistung_id'] ?? null)) {
            $leistung = CustomerService::query()->find($eingabe['kundenleistung_id']);

            // Eine Projektposition greift nicht auf Vertraege anderer Kunden zu.
            if (! $leistung instanceof CustomerService || $leistung->customer_id !== $projekt->customer_id) {
                return Response::error('Die Kundenleistung gehört nicht zum Kunden dieses Projekts.');
            }

            return $this->savePosition->suggestionFromService($leistung);
        }

        if (filled($eingabe['produkt_id'] ?? null)) {
            return $this->savePosition->suggestionFromProduct(Product::query()->findOrFail($eingabe['produkt_id']));
        }

        return [];
    }

    private function antwort(Project $projekt, string $vorgang, ?ProjectPosition $position): Response
    {
        return Response::json([
            'vorgang' => $vorgang,
            'projekt_id' => $projekt->id,
            'position' => $position === null ? null : [
                'id' => $position->id,
                'name' => $position->name,
                'herkunft' => $position->source(),
                'art' => $position->kind->value,
                'menge' => (float) $position->quantity,
                'einzelpreis' => $this->money($position->unit_price_cents),
                'gesamt' => $this->money($position->total()->cents),
            ],
            'volumen_einmalig' => $this->money($projekt->oneTimeVolume()->cents),
            'volumen_wiederkehrend' => $this->money($projekt->recurringVolume()->cents),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'projekt_id' => $schema->integer()->description('Projekt, zu dem die Position gehört.')->required(),
            'id' => $schema->integer()->description('Interne ID zum Ändern oder Entfernen.'),
            'produkt_id' => $schema->integer()->description('Katalogartikel als Herkunft; liefert Name, Preis und Art als Vorschlag.'),
            'kundenleistung_id' => $schema->integer()->description('Bestehende Kundenleistung als Herkunft. Muss zum Kunden des Projekts gehören.'),
            'name' => $schema->string()->description('Bezeichnung; überschreibt den Vorschlag aus der Herkunft.'),
            'art' => $schema->string()->description('one_time oder recurring. Nur einmalige Positionen bilden das Projektvolumen.'),
            'menge' => $schema->number()->description('Menge, auch mit Nachkommastellen.'),
            'einzelpreis_cents' => $schema->integer()->description('Einzelpreis in ganzen Cent.'),
            'status' => $schema->string()->description('planned, active, paused oder ended.'),
            'sortierung' => $schema->integer()->description('Position in der Liste.'),
            'entfernen' => $schema->boolean()->description('true entfernt die Position endgültig.'),
        ];
    }
}
