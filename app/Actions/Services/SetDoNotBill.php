<?php

namespace App\Actions\Services;

use App\Enums\DoNotBillReason;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\CustomerService;

/**
 * Setzt oder entfernt die Kennzeichnung „Bewusst nicht abrechnen".
 *
 * Die Kennzeichnung gilt, bis sie manuell entfernt wird. Nach dem Entfernen
 * beginnt die normale Betrachtung erst ab diesem Zeitpunkt — es gibt keine
 * rueckwirkende automatische Nachberechnung.
 */
class SetDoNotBill
{
    public function mark(CustomerService $service, DoNotBillReason $reason): CustomerService
    {
        $this->guardAgainstArchived($service);

        $service->forceFill([
            'do_not_bill' => true,
            'do_not_bill_reason' => $reason,
            'do_not_bill_since' => now(),
            'do_not_bill_released_at' => null,
        ])->save();

        return $service;
    }

    public function release(CustomerService $service): CustomerService
    {
        $this->guardAgainstArchived($service);

        if (! $service->do_not_bill) {
            return $service;
        }

        $service->forceFill([
            'do_not_bill' => false,
            'do_not_bill_reason' => null,
            'do_not_bill_released_at' => now(),
        ])->save();

        return $service;
    }

    private function guardAgainstArchived(CustomerService $service): void
    {
        if ($service->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Kundenleistungen können nicht mehr verändert werden.'
            );
        }
    }
}
