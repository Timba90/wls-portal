<?php

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Exceptions\ArchivingNotPossibleException;
use App\Models\Customer;

/**
 * Archiviert einen Kunden.
 *
 * Es gibt keine endgueltige Loeschung ueber die normale Oberflaeche.
 */
class ArchiveCustomer
{
    public function __invoke(Customer $customer): Customer
    {
        if ($customer->isArchived()) {
            return $customer;
        }

        $this->guardAgainstActiveServices($customer);

        $customer->forceFill([
            'status' => CustomerStatus::Archived,
            'archived_at' => now(),
        ])->save();

        return $customer;
    }

    /**
     * Ein Kunde darf nur archiviert werden, wenn keine aktiven Leistungen mehr
     * bestehen.
     */
    private function guardAgainstActiveServices(Customer $customer): void
    {
        $aktiveLeistungen = $customer->services()->active()->count();

        if ($aktiveLeistungen > 0) {
            throw new ArchivingNotPossibleException(
                $aktiveLeistungen === 1
                    ? 'Der Kunde besitzt noch eine aktive Leistung und kann deshalb nicht archiviert werden.'
                    : "Der Kunde besitzt noch {$aktiveLeistungen} aktive Leistungen und kann deshalb nicht archiviert werden."
            );
        }
    }
}
