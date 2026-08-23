<?php

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Legt einen Firmen- oder Privatkunden an und vergibt die Kundennummer.
 *
 * @phpstan-type CustomerInput array{
 *     type: string,
 *     company_name?: ?string,
 *     salutation?: ?string,
 *     academic_title?: ?string,
 *     first_name?: ?string,
 *     last_name?: ?string,
 *     birth_date?: ?string,
 *     gender?: ?string,
 *     short_label: string,
 *     internal_code: string,
 *     responsible_user_id?: ?int,
 *     emails?: array<int, array{email: string, type: string, is_primary?: bool}>,
 *     phones?: array<int, array{number: string, type: string, is_primary?: bool}>,
 * }
 */
class CreateCustomer
{
    public function __construct(
        private readonly GenerateCustomerNumber $generateCustomerNumber,
        private readonly SyncContactChannels $syncContactChannels,
    ) {}

    /**
     * @param  CustomerInput  $attributes
     */
    public function __invoke(array $attributes): Customer
    {
        return DB::transaction(function () use ($attributes): Customer {
            $type = CustomerType::from($attributes['type']);

            $customer = new Customer;
            $customer->fill($this->attributesForType($type, $attributes));
            $customer->customer_number = ($this->generateCustomerNumber)();
            $customer->status = CustomerStatus::Active;
            $customer->save();

            // Nur Privatkunden besitzen eigene Kontaktkanaele; bei Firmenkunden
            // haengen sie an den Ansprechpartnern.
            if ($type === CustomerType::Private) {
                ($this->syncContactChannels)(
                    $customer,
                    $attributes['emails'] ?? [],
                    $attributes['phones'] ?? [],
                );
            }

            return $customer;
        });
    }

    /**
     * Leert die Felder, die fuer den gewaehlten Kundentyp nicht gelten.
     *
     * @param  CustomerInput  $attributes
     * @return array<string, mixed>
     */
    private function attributesForType(CustomerType $type, array $attributes): array
    {
        $common = [
            'type' => $type,
            'short_label' => $attributes['short_label'],
            'internal_code' => $attributes['internal_code'],
            'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
        ];

        if ($type === CustomerType::Company) {
            return [
                ...$common,
                'company_name' => $attributes['company_name'] ?? null,
                'salutation' => null,
                'academic_title' => null,
                'first_name' => null,
                'last_name' => null,
                'birth_date' => null,
                'gender' => null,
            ];
        }

        return [
            ...$common,
            'company_name' => null,
            'salutation' => $attributes['salutation'] ?? null,
            'academic_title' => $attributes['academic_title'] ?? null,
            'first_name' => $attributes['first_name'] ?? null,
            'last_name' => $attributes['last_name'] ?? null,
            'birth_date' => $attributes['birth_date'] ?? null,
            'gender' => $attributes['gender'] ?? null,
        ];
    }
}
