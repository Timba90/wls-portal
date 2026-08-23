<?php

namespace App\Actions\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Archiviert einen Katalogartikel samt seinen Varianten.
 *
 * Bestehende Kundenleistungen bleiben unveraendert — die Verbindung zum Katalog
 * geht nicht verloren.
 */
class ArchiveProduct
{
    public function __invoke(Product $product): Product
    {
        if ($product->isArchived()) {
            return $product;
        }

        DB::transaction(function () use ($product): void {
            $product->forceFill([
                'status' => CatalogStatus::Archived,
                'archived_at' => now(),
            ])->save();

            $product->variants()->each(fn (ProductVariant $variant) => $variant->forceFill([
                'status' => CatalogStatus::Archived,
                'archived_at' => now(),
            ])->save());
        });

        return $product;
    }
}
