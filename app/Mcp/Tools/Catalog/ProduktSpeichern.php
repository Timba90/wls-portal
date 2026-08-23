<?php

namespace App\Mcp\Tools\Catalog;

use App\Actions\Catalog\SaveProduct;
use App\Mcp\Tools\PortalTool;
use App\Models\Product;
use App\Models\ServiceComponent;
use App\Models\Tag;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('produkt-speichern')]
#[Description('Legt einen Katalogartikel an oder ändert einen bestehenden. Preise werden in ganzen Cent angegeben. Änderungen am Katalog wirken nicht rückwirkend auf bestehende Kundenleistungen.')]
class ProduktSpeichern extends PortalTool
{
    public function __construct(private readonly SaveProduct $saveProduct) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'interner_name' => ['nullable', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string'],
            'kategorie_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unterkategorie_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'string', 'in:active,archived'],
            'einkaufspreis_cents' => ['nullable', 'integer', 'min:0'],
            'verkaufspreis_cents' => ['nullable', 'integer', 'min:0'],
            'abrechnungsintervall_einheit' => ['nullable', 'string', 'in:once,day,week,month,year'],
            'abrechnungsintervall_anzahl' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:60'],
            'leistungsbestandteile' => ['nullable', 'array'],
            'leistungsbestandteile.*.name' => ['required', 'string', 'max:255'],
            'leistungsbestandteile.*.beschreibung' => ['nullable', 'string'],
        ]);

        $produkt = filled($eingabe['id'] ?? null)
            ? Product::query()->with(['tags', 'serviceComponents'])->find($eingabe['id'])
            : null;

        if (filled($eingabe['id'] ?? null) && ! $produkt instanceof Product) {
            return Response::error('Katalogartikel nicht gefunden.');
        }

        if (! $produkt instanceof Product) {
            foreach (['name', 'interner_name', 'abrechnungsintervall_einheit'] as $pflichtfeld) {
                if (blank($eingabe[$pflichtfeld] ?? null)) {
                    return Response::error("Beim Anlegen ist „{$pflichtfeld}\" erforderlich.");
                }
            }
        }

        $gespeichert = ($this->saveProduct)(
            $this->attributes($eingabe, $produkt),
            $this->tags($eingabe, $produkt),
            $this->components($eingabe, $produkt),
            $produkt,
        );

        return Response::json([
            'vorgang' => $produkt instanceof Product ? 'geändert' : 'angelegt',
            'id' => $gespeichert->id,
            'name' => $gespeichert->name,
            'status' => $gespeichert->status->value,
            'standard_verkaufspreis' => $this->money($gespeichert->default_sales_price_cents),
            'standard_marge' => $this->money($gespeichert->defaultMargin()->cents),
        ]);
    }

    /**
     * Ergaenzt fehlende Felder aus dem Bestand, damit eine gezielte Aenderung
     * die uebrigen Werte nicht zuruecksetzt.
     *
     * @param  array<string, mixed>  $eingabe
     * @return array<string, mixed>
     */
    private function attributes(array $eingabe, ?Product $produkt): array
    {
        $einkauf = $eingabe['einkaufspreis_cents'] ?? $produkt?->default_purchase_price_cents ?? 0;
        $verkauf = $eingabe['verkaufspreis_cents'] ?? $produkt?->default_sales_price_cents ?? 0;

        return [
            'name' => $eingabe['name'] ?? $produkt?->name,
            'internal_name' => $eingabe['interner_name'] ?? $produkt?->internal_name,
            'description' => $eingabe['beschreibung'] ?? $produkt?->description,
            'category_id' => $eingabe['kategorie_id'] ?? $produkt?->category_id,
            'subcategory_id' => $eingabe['unterkategorie_id'] ?? $produkt?->subcategory_id,
            'status' => $eingabe['status'] ?? $produkt?->status->value ?? 'active',
            // Die Action erwartet eine Eingabe in Euro; der Umweg ueber
            // toInput() haelt den Centbetrag dabei exakt.
            'default_purchase_price' => Money::fromCents((int) $einkauf)->toInput(),
            'default_sales_price' => Money::fromCents((int) $verkauf)->toInput(),
            'default_billing_interval_unit' => $eingabe['abrechnungsintervall_einheit']
                ?? $produkt?->default_billing_interval_unit->value,
            'default_billing_interval_count' => $eingabe['abrechnungsintervall_anzahl']
                ?? $produkt?->default_billing_interval_count,
        ];
    }

    /**
     * @param  array<string, mixed>  $eingabe
     * @return array<int, int|string>
     */
    private function tags(array $eingabe, ?Product $produkt): array
    {
        if (array_key_exists('tags', $eingabe) && ! is_null($eingabe['tags'])) {
            return $eingabe['tags'];
        }

        return $produkt?->tags->map(fn (Tag $tag): string => $tag->name)->all() ?? [];
    }

    /**
     * @param  array<string, mixed>  $eingabe
     * @return array<int, array<string, mixed>>
     */
    private function components(array $eingabe, ?Product $produkt): array
    {
        if (array_key_exists('leistungsbestandteile', $eingabe) && ! is_null($eingabe['leistungsbestandteile'])) {
            return collect($eingabe['leistungsbestandteile'])->map(fn (array $bestandteil): array => [
                'name' => $bestandteil['name'],
                'description' => $bestandteil['beschreibung'] ?? null,
            ])->all();
        }

        return $produkt?->serviceComponents->map(fn (ServiceComponent $bestandteil): array => [
            'name' => $bestandteil->name,
            'description' => $bestandteil->description,
        ])->all() ?? [];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Zum Ändern angeben, zum Anlegen weglassen.'),
            'name' => $schema->string()->description('Pflicht beim Anlegen.'),
            'interner_name' => $schema->string()->description('Pflicht beim Anlegen.'),
            'beschreibung' => $schema->string(),
            'kategorie_id' => $schema->integer(),
            'unterkategorie_id' => $schema->integer()
                ->description('Muss unterhalb der angegebenen Kategorie liegen.'),
            'status' => $schema->string()->enum(['active', 'archived']),
            'einkaufspreis_cents' => $schema->integer()->description('Standard-Einkaufspreis in ganzen Cent.'),
            'verkaufspreis_cents' => $schema->integer()->description('Standard-Verkaufspreis in ganzen Cent.'),
            'abrechnungsintervall_einheit' => $schema->string()
                ->enum(['once', 'day', 'week', 'month', 'year'])
                ->description('Pflicht beim Anlegen. „once" bedeutet einmalig.'),
            'abrechnungsintervall_anzahl' => $schema->integer()
                ->description('Vielfaches der Einheit, etwa 3 für vierteljährlich. Bei „once" ohne Bedeutung.'),
            'tags' => $schema->array()
                ->description('Tag-Namen. Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten.'),
            'leistungsbestandteile' => $schema->array()
                ->description('Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten. Je Eintrag: name, beschreibung.'),
        ];
    }
}
