<?php

namespace App\Actions\Services;

use App\Actions\Catalog\SyncServiceComponents;
use App\Actions\Catalog\SyncTags;
use App\Enums\BillingIntervalUnit;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\CustomerService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Aktualisiert eine Kundenleistung.
 *
 * Die Verbindung zum Katalogartikel bleibt bestehen; der Katalog-Snapshot wird
 * bewusst nicht neu geschrieben, damit Abweichungen sichtbar bleiben.
 */
class UpdateCustomerService
{
    public function __construct(
        private readonly SyncTags $syncTags,
        private readonly SyncServiceComponents $syncServiceComponents,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $tags
     * @param  array<int, array<string, mixed>>  $components
     */
    public function __invoke(
        CustomerService $service,
        array $attributes,
        array $tags = [],
        array $components = [],
    ): CustomerService {
        if ($service->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Kundenleistungen können nicht mehr verändert werden.'
            );
        }

        return DB::transaction(function () use ($service, $attributes, $tags, $components): CustomerService {
            $unit = BillingIntervalUnit::from($attributes['billing_interval_unit']);

            $service->update([
                'name' => $attributes['name'],
                'billing_label' => $attributes['billing_label'] ?? null,
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'] ?? $service->status,
                'purchase_price_cents' => Money::fromEuroInput($attributes['purchase_price'] ?? null)->cents,
                'sales_price_cents' => Money::fromEuroInput($attributes['sales_price'] ?? null)->cents,
                'billing_interval_unit' => $unit,
                'billing_interval_count' => $unit->requiresCount()
                    ? max(1, (int) ($attributes['billing_interval_count'] ?? 1))
                    : null,
                'service_start_date' => $attributes['service_start_date'] ?? null,
                'billing_start_date' => $attributes['billing_start_date'] ?? null,
                'first_billing_date' => $attributes['first_billing_date'] ?? null,
                'category_id' => $attributes['category_id'] ?? null,
                'subcategory_id' => $attributes['subcategory_id'] ?? null,
                'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
            ]);

            ($this->syncTags)($service, $tags);
            ($this->syncServiceComponents)($service, $components);

            return $service;
        });
    }
}
