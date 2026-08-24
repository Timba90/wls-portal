<?php

namespace App\Actions\Services;

use App\Models\CustomerService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Findet die Kundenleistungen mit offener Katalogaenderung.
 *
 * Der Vergleich laesst sich nicht in SQL fuehren: der gesehene Stand liegt als
 * JSON vor und die heutigen Katalogwerte haengen an Artikel und Variante. Die
 * Kandidatenmenge ist aber eng — nur Leistungen mit Katalogherkunft, die nicht
 * archiviert sind — und wird mit ihren Beziehungen in einem Zug geladen.
 * Dieselbe Linie wie AE-13a: Kennzahlen in PHP statt in SQL.
 */
class FindServicesWithCatalogChanges
{
    public function __construct(private readonly CompareWithCatalog $compareWithCatalog) {}

    /**
     * @return array<int, int>
     */
    public function __invoke(): array
    {
        return CustomerService::query()
            ->whereNotNull('product_id')
            ->whereNotNull('catalog_snapshot')
            ->whereNot('status', 'archived')
            ->with(['product', 'productVariant'])
            ->get()
            ->filter(fn (CustomerService $leistung): bool => $this->compareWithCatalog->hasOpenChanges($leistung))
            ->modelKeys();
    }

    /**
     * Die betroffenen Leistungen eines einzelnen Katalogartikels.
     *
     * @return array<int, int>
     */
    public function forProduct(int $productId): array
    {
        return CustomerService::query()
            ->where('product_id', $productId)
            ->whereNotNull('catalog_snapshot')
            ->whereNot('status', 'archived')
            ->with(['product', 'productVariant'])
            ->get()
            ->filter(fn (CustomerService $leistung): bool => $this->compareWithCatalog->hasOpenChanges($leistung))
            ->modelKeys();
    }

    /**
     * @param  Builder<CustomerService>  $query
     */
    public function applyTo(Builder $query): void
    {
        $query->whereKey($this());
    }
}
