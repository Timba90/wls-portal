<?php

namespace App\Mcp\Tools\Catalog;

use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ServiceComponent;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('produkt-lesen')]
#[Description('Liefert einen Katalogartikel vollständig: Standardwerte, Varianten mit ihren Abweichungen, Leistungsbestandteile, Tags und die Zahl der darauf beruhenden Kundenleistungen.')]
#[IsReadOnly]
class ProduktLesen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $produkt = Product::query()
            ->with(['category', 'subcategory', 'variants', 'serviceComponents', 'tags'])
            ->find($eingabe['id']);

        if (! $produkt instanceof Product) {
            return Response::error('Katalogartikel nicht gefunden.');
        }

        return Response::json([
            'id' => $produkt->id,
            'name' => $produkt->name,
            'interner_name' => $produkt->internal_name,
            'beschreibung' => $produkt->description,
            'status' => $produkt->status->value,
            'kategorie' => $produkt->category?->only(['id', 'name']),
            'unterkategorie' => $produkt->subcategory?->only(['id', 'name']),
            'standard_einkaufspreis' => $this->money($produkt->default_purchase_price_cents),
            'standard_verkaufspreis' => $this->money($produkt->default_sales_price_cents),
            'standard_marge' => $this->money($produkt->defaultMargin()->cents),
            'standard_marge_prozent' => $produkt->defaultMarginPercentage(),
            'abrechnungsintervall' => [
                'einheit' => $produkt->default_billing_interval_unit->value,
                'anzahl' => $produkt->default_billing_interval_count,
                'bezeichnung' => $produkt->defaultBillingInterval()->label(),
            ],
            'varianten' => $produkt->variants->map(fn (ProductVariant $variante): array => [
                'id' => $variante->id,
                'name' => $variante->name,
                'einkaufspreis' => $this->money($variante->effectivePurchasePrice()->cents),
                'verkaufspreis' => $this->money($variante->effectiveSalesPrice()->cents),
                'abrechnungsintervall' => $variante->effectiveBillingInterval()->label(),
                'erbt_preise' => is_null($variante->purchase_price_cents) && is_null($variante->sales_price_cents),
            ])->all(),
            'leistungsbestandteile' => $produkt->serviceComponents
                ->map(fn (ServiceComponent $bestandteil): array => [
                    'id' => $bestandteil->id,
                    'name' => $bestandteil->name,
                    'beschreibung' => $bestandteil->description,
                ])->all(),
            'tags' => $produkt->tags->map(fn (Tag $tag): string => $tag->name)->all(),
            'anzahl_kundenleistungen' => CustomerService::query()
                ->where('product_id', $produkt->id)
                ->count(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Katalogartikels.')->required(),
        ];
    }
}
