<?php

namespace App\Mcp\Tools\Services;

use App\Actions\Services\CreateCustomerService;
use App\Actions\Services\UpdateCustomerService;
use App\Mcp\Tools\PortalTool;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\ServiceComponent;
use App\Models\Tag;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('leistung-speichern')]
#[Description('Legt eine Kundenleistung an oder ändert eine bestehende. Preise werden in ganzen Cent angegeben. Beim Anlegen mit Katalogartikel entsteht ein Snapshot, der spätere Abweichungen nachvollziehbar hält. Archivierte Leistungen sind schreibgeschützt.')]
class LeistungSpeichern extends PortalTool
{
    public function __construct(
        private readonly CreateCustomerService $createCustomerService,
        private readonly UpdateCustomerService $updateCustomerService,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'kunde_id' => ['nullable', 'integer', 'exists:customers,id'],
            'produkt_id' => ['nullable', 'integer', 'exists:products,id'],
            'variante_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'abrechnungsbezeichnung' => ['nullable', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:planned,active,paused,ended'],
            'einkaufspreis_cents' => ['nullable', 'integer', 'min:0'],
            'verkaufspreis_cents' => ['nullable', 'integer', 'min:0'],
            'abrechnungsintervall_einheit' => ['nullable', 'string', 'in:once,day,week,month,year'],
            'abrechnungsintervall_anzahl' => ['nullable', 'integer', 'min:1'],
            'leistungsbeginn' => ['nullable', 'date'],
            'abrechnungsbeginn' => ['nullable', 'date'],
            'erste_abrechnung' => ['nullable', 'date'],
            'kategorie_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unterkategorie_id' => ['nullable', 'integer', 'exists:categories,id'],
            'verantwortlicher_id' => ['nullable', 'integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:60'],
            'leistungsbestandteile' => ['nullable', 'array'],
            'leistungsbestandteile.*.name' => ['required', 'string', 'max:255'],
            'leistungsbestandteile.*.beschreibung' => ['nullable', 'string'],
        ]);

        return filled($eingabe['id'] ?? null)
            ? $this->update($eingabe)
            : $this->create($eingabe);
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function create(array $eingabe): Response
    {
        foreach (['kunde_id', 'name', 'abrechnungsintervall_einheit'] as $pflichtfeld) {
            if (blank($eingabe[$pflichtfeld] ?? null)) {
                return Response::error("Beim Anlegen ist „{$pflichtfeld}\" erforderlich.");
            }
        }

        $kunde = Customer::query()->find($eingabe['kunde_id']);

        if (! $kunde instanceof Customer) {
            return Response::error('Kunde nicht gefunden.');
        }

        $leistung = ($this->createCustomerService)(
            $kunde,
            $this->attributes($eingabe, null),
            $eingabe['tags'] ?? [],
            $this->components($eingabe, null),
        );

        return $this->respond($leistung, 'angelegt');
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function update(array $eingabe): Response
    {
        $leistung = CustomerService::query()
            ->with(['tags', 'serviceComponents'])
            ->find($eingabe['id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        if ($leistung->isArchived()) {
            return Response::error(
                'Archivierte Kundenleistungen sind schreibgeschützt. Zuerst über leistung-status-setzen reaktivieren.'
            );
        }

        $leistung = ($this->updateCustomerService)(
            $leistung,
            $this->attributes($eingabe, $leistung),
            $this->tags($eingabe, $leistung),
            $this->components($eingabe, $leistung),
        );

        return $this->respond($leistung, 'geändert');
    }

    /**
     * Ergaenzt fehlende Felder aus dem Bestand, damit eine gezielte Aenderung
     * die uebrigen Werte nicht zuruecksetzt.
     *
     * @param  array<string, mixed>  $eingabe
     * @return array<string, mixed>
     */
    private function attributes(array $eingabe, ?CustomerService $leistung): array
    {
        $einkauf = $eingabe['einkaufspreis_cents'] ?? $leistung?->purchase_price_cents ?? 0;
        $verkauf = $eingabe['verkaufspreis_cents'] ?? $leistung?->sales_price_cents ?? 0;

        return [
            'product_id' => $eingabe['produkt_id'] ?? $leistung?->product_id,
            'product_variant_id' => $eingabe['variante_id'] ?? $leistung?->product_variant_id,
            'name' => $eingabe['name'] ?? $leistung?->name,
            'billing_label' => $eingabe['abrechnungsbezeichnung'] ?? $leistung?->billing_label,
            'description' => $eingabe['beschreibung'] ?? $leistung?->description,
            'status' => $eingabe['status'] ?? $leistung?->status->value ?? 'planned',
            // Die Actions erwarten eine Eingabe in Euro; toInput() haelt den
            // Centbetrag dabei exakt.
            'purchase_price' => Money::fromCents((int) $einkauf)->toInput(),
            'sales_price' => Money::fromCents((int) $verkauf)->toInput(),
            'billing_interval_unit' => $eingabe['abrechnungsintervall_einheit']
                ?? $leistung?->billing_interval_unit->value,
            'billing_interval_count' => $eingabe['abrechnungsintervall_anzahl']
                ?? $leistung?->billing_interval_count,
            'service_start_date' => $eingabe['leistungsbeginn'] ?? $this->date($leistung?->service_start_date),
            'billing_start_date' => $eingabe['abrechnungsbeginn'] ?? $this->date($leistung?->billing_start_date),
            'first_billing_date' => $eingabe['erste_abrechnung'] ?? $this->date($leistung?->first_billing_date),
            'category_id' => $eingabe['kategorie_id'] ?? $leistung?->category_id,
            'subcategory_id' => $eingabe['unterkategorie_id'] ?? $leistung?->subcategory_id,
            'responsible_user_id' => $eingabe['verantwortlicher_id'] ?? $leistung?->responsible_user_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $eingabe
     * @return array<int, int|string>
     */
    private function tags(array $eingabe, ?CustomerService $leistung): array
    {
        if (array_key_exists('tags', $eingabe) && ! is_null($eingabe['tags'])) {
            return $eingabe['tags'];
        }

        return $leistung?->tags->map(fn (Tag $tag): string => $tag->name)->all() ?? [];
    }

    /**
     * @param  array<string, mixed>  $eingabe
     * @return array<int, array<string, mixed>>
     */
    private function components(array $eingabe, ?CustomerService $leistung): array
    {
        if (array_key_exists('leistungsbestandteile', $eingabe) && ! is_null($eingabe['leistungsbestandteile'])) {
            return collect($eingabe['leistungsbestandteile'])->map(fn (array $bestandteil): array => [
                'name' => $bestandteil['name'],
                'description' => $bestandteil['beschreibung'] ?? null,
            ])->all();
        }

        return $leistung?->serviceComponents->map(fn (ServiceComponent $bestandteil): array => [
            'name' => $bestandteil->name,
            'description' => $bestandteil->description,
        ])->all() ?? [];
    }

    private function respond(CustomerService $leistung, string $vorgang): Response
    {
        return Response::json([
            'vorgang' => $vorgang,
            'id' => $leistung->id,
            'name' => $leistung->name,
            'kunde_id' => $leistung->customer_id,
            'status' => $leistung->status->value,
            'verkaufspreis' => $this->money($leistung->sales_price_cents),
            'marge' => $this->money($leistung->margin()->cents),
            'abrechnungsintervall' => $leistung->billingInterval()->label(),
            'umsatz_monat' => $this->money($leistung->monthlyRevenue()->cents),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Zum Ändern angeben, zum Anlegen weglassen.'),
            'kunde_id' => $schema->integer()->description('Pflicht beim Anlegen. Lässt sich nachträglich nicht wechseln.'),
            'produkt_id' => $schema->integer()->description('Katalogartikel, auf dem die Leistung beruht. Ohne Angabe entsteht eine individuelle Leistung.'),
            'variante_id' => $schema->integer()->description('Variante des Katalogartikels.'),
            'name' => $schema->string()->description('Pflicht beim Anlegen.'),
            'abrechnungsbezeichnung' => $schema->string()->description('Abweichender Text für die Rechnung.'),
            'beschreibung' => $schema->string(),
            'status' => $schema->string()->enum(['planned', 'active', 'paused', 'ended'])
                ->description('Archivieren läuft über leistung-status-setzen.'),
            'einkaufspreis_cents' => $schema->integer()->description('Einkaufspreis in ganzen Cent.'),
            'verkaufspreis_cents' => $schema->integer()
                ->description('Verkaufspreis in ganzen Cent. Setzt den Preis sofort; für einen geplanten Wechsel preisaenderung-planen verwenden.'),
            'abrechnungsintervall_einheit' => $schema->string()
                ->enum(['once', 'day', 'week', 'month', 'year'])
                ->description('Pflicht beim Anlegen. „once" bedeutet einmalig.'),
            'abrechnungsintervall_anzahl' => $schema->integer()
                ->description('Vielfaches der Einheit, etwa 3 für vierteljährlich.'),
            'leistungsbeginn' => $schema->string()->description('Datum in der Form JJJJ-MM-TT.'),
            'abrechnungsbeginn' => $schema->string()->description('Datum in der Form JJJJ-MM-TT.'),
            'erste_abrechnung' => $schema->string()->description('Datum in der Form JJJJ-MM-TT.'),
            'kategorie_id' => $schema->integer(),
            'unterkategorie_id' => $schema->integer(),
            'verantwortlicher_id' => $schema->integer()->description('Benutzer-ID des internen Verantwortlichen.'),
            'tags' => $schema->array()
                ->description('Tag-Namen. Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten.'),
            'leistungsbestandteile' => $schema->array()
                ->description('Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten. Je Eintrag: name, beschreibung.'),
        ];
    }
}
