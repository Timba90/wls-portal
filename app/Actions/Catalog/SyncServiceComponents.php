<?php

namespace App\Actions\Catalog;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Setzt die Leistungsbestandteile eines Katalogartikels, einer Variante oder
 * einer Kundenleistung neu.
 *
 * Die Reihenfolge ergibt sich aus der uebergebenen Reihenfolge.
 */
class SyncServiceComponents
{
    /**
     * @param  array<int, array{id?: ?int, title: string, description?: ?string, purchase_price?: string|int|float|null, sales_price?: string|int|float|null}>  $components
     */
    public function __invoke(Model $owner, array $components): void
    {
        DB::transaction(function () use ($owner, $components): void {
            $components = array_values(array_filter(
                $components,
                fn (array $component): bool => filled(trim((string) ($component['title'] ?? ''))),
            ));

            $keptIds = array_values(array_filter(array_column($components, 'id')));

            $owner->serviceComponents()
                ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
                ->delete();

            foreach ($components as $index => $component) {
                $values = [
                    'title' => trim((string) $component['title']),
                    'description' => filled($component['description'] ?? null) ? $component['description'] : null,
                    'sort_order' => $index,
                    'purchase_price_cents' => $this->toCents($component['purchase_price'] ?? null),
                    'sales_price_cents' => $this->toCents($component['sales_price'] ?? null),
                ];

                if (filled($component['id'] ?? null)) {
                    $owner->serviceComponents()->whereKey($component['id'])->update($values);

                    continue;
                }

                $owner->serviceComponents()->create($values);
            }

            $owner->unsetRelation('serviceComponents');
        });
    }

    /**
     * Preise an Leistungsbestandteilen sind optional.
     */
    private function toCents(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::fromEuroInput($value)->cents;
    }
}
