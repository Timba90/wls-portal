<?php

namespace App\Mcp\Tools\Catalog;

use App\Actions\Maintenance\DeletePermanently;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('produkt-loeschen')]
#[Description('Entfernt einen Katalogartikel endgültig, mitsamt seinen Varianten und Leistungsbestandteilen. Darauf beruhende Kundenleistungen bleiben bestehen und verlieren nur den Katalogbezug; ihr Snapshot bewahrt die Herkunft. Nicht umkehrbar.')]
#[IsDestructive]
class ProduktLoeschen extends PortalTool
{
    public function __construct(private readonly DeletePermanently $deletePermanently) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'bestaetigung' => ['required', 'string'],
        ]);

        $produkt = Product::query()->find($eingabe['id']);

        if (! $produkt instanceof Product) {
            return Response::error('Katalogartikel nicht gefunden.');
        }

        if ($eingabe['bestaetigung'] !== $produkt->internal_name) {
            return Response::error(
                "Bestätigung stimmt nicht. Erwartet wird der interne Name „{$produkt->internal_name}\"."
            );
        }

        $name = $produkt->name;

        $betroffeneLeistungen = CustomerService::query()
            ->where('product_id', $produkt->id)
            ->count();

        $entfernt = ($this->deletePermanently)($produkt);

        return Response::json([
            'entfernt' => true,
            'name' => $name,
            'mit_entfernt' => $entfernt,
            'entkoppelte_kundenleistungen' => $betroffeneLeistungen,
            'hinweis' => 'Bestehende Kundenleistungen behalten ihre Werte und ihren Katalog-Snapshot.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Katalogartikels.')->required(),
            'bestaetigung' => $schema->string()
                ->description('Zur Sicherheit der interne Name des betroffenen Artikels.')
                ->required(),
        ];
    }
}
