<?php

namespace App\Mcp\Tools\Catalog;

use App\Enums\CatalogStatus;
use App\Mcp\Tools\PortalTool;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('katalog-suchen')]
#[Description('Durchsucht die Katalogartikel nach Name, internem Namen oder Beschreibung und liefert Standardpreise, Marge und Abrechnungsintervall.')]
#[IsReadOnly]
class KatalogSuchen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'suchbegriff' => ['nullable', 'string', 'max:255'],
            'kategorie_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:active,archived'],
            'anzahl' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::query()
            ->with(['category', 'subcategory'])
            ->withCount('variants');

        $this->applySearch($query, $eingabe['suchbegriff'] ?? null, ['name', 'internal_name', 'description']);

        if (filled($eingabe['kategorie_id'] ?? null)) {
            $query->where(function ($gruppe) use ($eingabe): void {
                $gruppe->where('category_id', $eingabe['kategorie_id'])
                    ->orWhere('subcategory_id', $eingabe['kategorie_id']);
            });
        }

        if (filled($eingabe['status'] ?? null)) {
            $query->where('status', CatalogStatus::from($eingabe['status']));
        }

        $artikel = $query->orderBy('name')
            ->limit($this->limit($eingabe['anzahl'] ?? null))
            ->get();

        return Response::json([
            'anzahl' => $artikel->count(),
            'artikel' => $artikel->map(fn (Product $produkt): array => [
                'id' => $produkt->id,
                'name' => $produkt->name,
                'interner_name' => $produkt->internal_name,
                'status' => $produkt->status->value,
                'kategorie' => $produkt->category?->name,
                'unterkategorie' => $produkt->subcategory?->name,
                'einkaufspreis' => $this->money($produkt->default_purchase_price_cents),
                'verkaufspreis' => $this->money($produkt->default_sales_price_cents),
                'marge' => $this->money($produkt->defaultMargin()->cents),
                'marge_prozent' => $produkt->defaultMarginPercentage(),
                'abrechnungsintervall' => $produkt->defaultBillingInterval()->label(),
                'anzahl_varianten' => $produkt->variants_count,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()->description('Freitext über Name, internen Namen und Beschreibung.'),
            'kategorie_id' => $schema->integer()->description('Trifft Kategorie und Unterkategorie gleichermaßen.'),
            'status' => $schema->string()->enum(['active', 'archived'])
                ->description('Ohne Angabe werden aktive und archivierte Artikel geliefert.'),
            'anzahl' => $schema->integer()->description('Höchstzahl der Treffer, Standard 25, Maximum 100.'),
        ];
    }
}
