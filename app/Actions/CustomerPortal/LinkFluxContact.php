<?php

namespace App\Actions\CustomerPortal;

use App\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Verknuepft einen Kunden mit seinem Flux-Kontakt oder loest die Verknuepfung.
 */
class LinkFluxContact
{
    public function __invoke(Customer $customer, mixed $fluxContactId): Customer
    {
        $validated = Validator::make(['flux_contact_id' => blank($fluxContactId) ? null : $fluxContactId], [
            'flux_contact_id' => ['nullable', 'integer', 'min:1', Rule::unique('customers', 'flux_contact_id')->ignore($customer->id)],
        ], [
            'flux_contact_id.unique' => 'Dieser Flux-Kontakt ist bereits einem anderen Kunden zugeordnet.',
        ], [
            'flux_contact_id' => 'Flux-Kontakt-ID',
        ])->validate();

        $customer->update(['flux_contact_id' => $validated['flux_contact_id']]);

        LoadCustomerInvoices::forget($customer);
        LoadSepaMandates::forget($customer);

        return $customer;
    }
}
