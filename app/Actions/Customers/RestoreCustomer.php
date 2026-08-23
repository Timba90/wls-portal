<?php

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;

/**
 * Hebt die Archivierung eines Kunden wieder auf.
 */
class RestoreCustomer
{
    public function __invoke(Customer $customer): Customer
    {
        if (! $customer->isArchived()) {
            return $customer;
        }

        $customer->forceFill([
            'status' => CustomerStatus::Active,
            'archived_at' => null,
        ])->save();

        return $customer;
    }
}
