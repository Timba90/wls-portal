<?php

namespace App\Actions\Contacts;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Setzt die Vertretungen eines Kunden neu.
 *
 * Je Kunde und Rolle sind mehrere Vertretungen mit Priorität moeglich.
 */
class SaveContactDeputies
{
    /**
     * @param  array<int, array{contact_role_id: int, contact_id: int, priority?: int}>  $deputies
     */
    public function __invoke(Customer $customer, array $deputies): void
    {
        DB::transaction(function () use ($customer, $deputies): void {
            $customer->contactDeputies()->delete();

            $seen = [];

            foreach ($deputies as $deputy) {
                if (blank($deputy['contact_role_id'] ?? null) || blank($deputy['contact_id'] ?? null)) {
                    continue;
                }

                $key = $deputy['contact_role_id'].'-'.$deputy['contact_id'];

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $customer->contactDeputies()->create([
                    'contact_role_id' => (int) $deputy['contact_role_id'],
                    'contact_id' => (int) $deputy['contact_id'],
                    'priority' => (int) ($deputy['priority'] ?? 100),
                ]);
            }

            $customer->unsetRelation('contactDeputies');
        });
    }
}
