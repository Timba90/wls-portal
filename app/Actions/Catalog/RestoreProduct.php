<?php

namespace App\Actions\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Product;

/**
 * Hebt die Archivierung eines Katalogartikels auf.
 *
 * Varianten bleiben archiviert und werden bei Bedarf einzeln reaktiviert.
 */
class RestoreProduct
{
    public function __invoke(Product $product): Product
    {
        if ($product->isArchived()) {
            $product->forceFill([
                'status' => CatalogStatus::Active,
                'archived_at' => null,
            ])->save();
        }

        return $product;
    }
}
