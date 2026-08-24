<?php

namespace App\Mcp\Tools\Services;

use App\Actions\Services\CompareWithCatalog;
use App\Actions\Services\FindServicesWithCatalogChanges;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('katalogabgleich-lesen')]
#[Description('Zeigt, wo sich der Katalog seit der Verknüpfung geändert hat. Ohne „id" alle betroffenen Kundenleistungen, mit „id" die Gegenüberstellung von zuletzt gesehenem Stand, heutigem Katalog und dem Wert der Leistung. Bestehende Leistungen werden nie automatisch angepasst.')]
#[IsReadOnly]
class KatalogabgleichLesen extends PortalTool
{
    public function __construct(
        private readonly CompareWithCatalog $compareWithCatalog,
        private readonly FindServicesWithCatalogChanges $findServices,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'artikel_id' => ['nullable', 'integer'],
        ]);

        if (filled($eingabe['id'] ?? null)) {
            return $this->einzeln((int) $eingabe['id']);
        }

        $ids = filled($eingabe['artikel_id'] ?? null)
            ? $this->findServices->forProduct((int) $eingabe['artikel_id'])
            : ($this->findServices)();

        $leistungen = CustomerService::query()
            ->with(['customer', 'product'])
            ->whereKey($ids)
            ->get();

        return Response::json([
            'anzahl' => $leistungen->count(),
            'leistungen' => $leistungen->map(fn (CustomerService $leistung): array => [
                'id' => $leistung->id,
                'name' => $leistung->name,
                'kunde' => $leistung->customer->displayName(),
                'kunde_id' => $leistung->customer_id,
                'artikel' => $leistung->product?->name,
                'artikel_id' => $leistung->product_id,
                'zuletzt_geprueft' => $this->dateTime($leistung->catalog_reviewed_at),
            ])->all(),
        ]);
    }

    private function einzeln(int $id): Response
    {
        $leistung = CustomerService::query()->with(['product', 'productVariant'])->find($id);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        return Response::json([
            'id' => $leistung->id,
            'name' => $leistung->name,
            'aus_katalog' => $leistung->hasCatalogOrigin(),
            'zuletzt_geprueft' => $this->dateTime($leistung->catalog_reviewed_at),
            'felder' => ($this->compareWithCatalog)($leistung),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Kundenleistung, deren Abgleich im Einzelnen interessiert.'),
            'artikel_id' => $schema->integer()->description('Nur die betroffenen Leistungen eines Katalogartikels.'),
        ];
    }
}
