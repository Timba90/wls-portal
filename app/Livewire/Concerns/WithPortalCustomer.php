<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Models\PortalUser;

/**
 * Zugriff auf den angemeldeten Kundenzugang und seinen Kunden.
 *
 * Jede Abfrage im Kundenportal geht von hier aus. Wer Daten laedt, ohne sie
 * ueber `customer()` einzuschraenken, zeigt womoeglich fremde Daten.
 */
trait WithPortalCustomer
{
    protected function portalUser(): PortalUser
    {
        /** @var PortalUser */
        return auth('customer')->user();
    }

    protected function customer(): Customer
    {
        return $this->portalUser()->customer;
    }
}
