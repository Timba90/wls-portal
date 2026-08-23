<?php

namespace App\Actions\Customers;

use App\Actions\Contacts\SyncContactChannels;
use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Aktualisiert die Stammdaten eines Kunden.
 *
 * Der Kundentyp und die Kundennummer bleiben unveraendert.
 */
class UpdateCustomer
{
    public function __construct(private readonly SyncContactChannels $syncContactChannels) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Customer $customer, array $attributes): Customer
    {
        return DB::transaction(function () use ($customer, $attributes): Customer {
            $customer->fill($customer->isCompany()
                ? [
                    'company_name' => $attributes['company_name'] ?? null,
                    'short_label' => $attributes['short_label'],
                    'internal_code' => $attributes['internal_code'],
                    'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
                ]
                : [
                    'salutation' => $attributes['salutation'] ?? null,
                    'academic_title' => $attributes['academic_title'] ?? null,
                    'first_name' => $attributes['first_name'] ?? null,
                    'last_name' => $attributes['last_name'] ?? null,
                    'birth_date' => $attributes['birth_date'] ?? null,
                    'gender' => $attributes['gender'] ?? null,
                    'short_label' => $attributes['short_label'],
                    'internal_code' => $attributes['internal_code'],
                    'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
                ]);

            $customer->save();

            if ($customer->type === CustomerType::Private) {
                ($this->syncContactChannels)(
                    $customer,
                    $attributes['emails'] ?? [],
                    $attributes['phones'] ?? [],
                );
            }

            return $customer;
        });
    }
}
