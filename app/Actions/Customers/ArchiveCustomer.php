<?php

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
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

        $customer->forceFill([
            'status' => CustomerStatus::Archived,
            'archived_at' => now(),
        ])->save();

        return $customer;
    }
}
