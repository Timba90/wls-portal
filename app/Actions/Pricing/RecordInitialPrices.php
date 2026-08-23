<?php

namespace App\Actions\Pricing;

use App\Enums\PriceType;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Models\User;

/**
 * Legt den Startpunkt des Preisverlaufs einer neuen Kundenleistung an.
 *
 * Der Verlauf beginnt damit lueckenlos beim vereinbarten Ausgangspreis.
 */
class RecordInitialPrices
{
    public function __invoke(CustomerService $service, ?User $user = null): void
    {
        foreach (PriceType::cases() as $type) {
            PriceChange::query()->create([
                'customer_service_id' => $service->getKey(),
                'price_type' => $type,
                'old_price_cents' => null,
                'new_price_cents' => $service->{$type->column()},
                'effective_date' => ($service->service_start_date ?? $service->created_at ?? now())->toDateString(),
                'applied_at' => now(),
                'user_id' => $user?->getKey(),
                'note' => 'Preis bei Anlage der Leistung',
            ]);
        }
    }
}
