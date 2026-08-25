<?php

namespace App\Actions\Projects;

use App\Exceptions\ReadOnlyRecordException;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectPosition;
use App\Support\Money;

/**
 * Legt eine Projektposition an oder aendert sie.
 *
 * Wird ein Katalogartikel oder eine Kundenleistung gewaehlt, uebernimmt die
 * Position deren Namen und Preis als Vorschlag — beides bleibt danach frei
 * aenderbar, weil ein Projekt vom Listenpreis abweichen darf.
 */
class SavePosition
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Project $project, array $attributes, ?ProjectPosition $position = null): ProjectPosition
    {
        if ($project->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Projekte können nicht mehr verändert werden.'
            );
        }

        $werte = [
            'product_id' => $attributes['product_id'] ?? null,
            'customer_service_id' => $attributes['customer_service_id'] ?? null,
            'name' => $attributes['name'],
            'kind' => $attributes['kind'],
            'quantity' => max(0, (float) ($attributes['quantity'] ?? 1)),
            'unit_price_cents' => Money::fromEuroInput($attributes['unit_price'] ?? null)->cents,
            'status' => $attributes['status'],
            'sort_order' => $attributes['sort_order'] ?? $this->nextSortOrder($project, $position),
        ];

        if ($position) {
            $position->update($werte);

            return $position;
        }

        return $project->positions()->create($werte);
    }

    /**
     * Vorschlagswerte aus einem Katalogartikel.
     *
     * @return array{name: string, unit_price: string, kind: string}
     */
    public function suggestionFromProduct(Product $product): array
    {
        return [
            'name' => $product->name,
            'unit_price' => $product->defaultSalesPrice()->toInput(),
            'kind' => $product->defaultBillingInterval()->isRecurring() ? 'recurring' : 'one_time',
        ];
    }

    /**
     * Vorschlagswerte aus einer bestehenden Kundenleistung.
     *
     * @return array{name: string, unit_price: string, kind: string}
     */
    public function suggestionFromService(CustomerService $service): array
    {
        return [
            'name' => $service->name,
            'unit_price' => $service->salesPrice()->toInput(),
            'kind' => $service->billingInterval()->isRecurring() ? 'recurring' : 'one_time',
        ];
    }

    private function nextSortOrder(Project $project, ?ProjectPosition $position): int
    {
        return $position?->sort_order ?? ((int) $project->positions()->max('sort_order') + 1);
    }
}
