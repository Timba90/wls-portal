<?php

namespace App\Actions\CustomerPortal;

use App\Models\Customer;
use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Support\Facades\Cache;

/**
 * Bestehende SEPA-Mandate eines Kunden aus Flux, mit maskierter IBAN.
 */
class LoadSepaMandates
{
    public const CACHE_SECONDS = 300;

    public function __construct(private readonly FluxClient $flux) {}

    /**
     * @return list<array{id: int, mandate_reference_number: string, signed_date: ?string, type: string, account_holder: ?string, iban: ?string, bank_name: ?string}>
     *
     * @throws FluxException
     */
    public function __invoke(Customer $customer): array
    {
        if (! $customer->isLinkedToFlux()) {
            return [];
        }

        return Cache::remember(
            self::cacheKey($customer),
            self::CACHE_SECONDS,
            fn (): array => $this->flux->sepaMandates($customer->flux_contact_id),
        );
    }

    public static function forget(Customer $customer): void
    {
        Cache::forget(self::cacheKey($customer));
    }

    private static function cacheKey(Customer $customer): string
    {
        return 'kundenportal:sepa:'.$customer->id.':'.$customer->flux_contact_id;
    }
}
