<?php

namespace App\Actions\CustomerPortal;

use App\Models\Customer;
use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Support\Facades\Cache;

/**
 * Rechnungen eines Kunden aus Flux.
 *
 * Flux ist die fuehrende Quelle; das Portal speichert nichts davon. Das
 * Ergebnis wird nur kurz zwischengespeichert, damit ein Seitenwechsel im
 * Portal nicht jedes Mal Flux abfragt.
 */
class LoadCustomerInvoices
{
    public const CACHE_SECONDS = 300;

    public function __construct(private readonly FluxClient $flux) {}

    /**
     * @return list<array{id: int, invoice_number: string, invoice_date: ?string, payment_target_date: ?string, total_net_price: string, total_gross_price: string, balance: ?string, payment_state: ?string}>
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
            fn (): array => $this->flux->invoices($customer->flux_contact_id),
        );
    }

    public static function forget(Customer $customer): void
    {
        Cache::forget(self::cacheKey($customer));
    }

    private static function cacheKey(Customer $customer): string
    {
        return 'kundenportal:rechnungen:'.$customer->id.':'.$customer->flux_contact_id;
    }
}
