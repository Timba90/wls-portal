<?php

namespace App\Actions\Services;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;

/**
 * Hebt die Archivierung einer Kundenleistung auf.
 *
 * Die Leistung kehrt als beendet zurueck und muss bewusst wieder aktiviert
 * werden.
 */
class RestoreCustomerService
{
    public function __invoke(CustomerService $service): CustomerService
    {
        if (! $service->isArchived()) {
            return $service;
        }

        $service->forceFill([
            'status' => CustomerServiceStatus::Ended,
            'archived_at' => null,
        ])->save();

        return $service;
    }
}
