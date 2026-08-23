<?php

namespace App\Actions\Services;

use App\Enums\CustomerServiceStatus;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\CustomerService;

/**
 * Wechselt den Status einer Kundenleistung.
 *
 * Der Status "archiviert" wird ausschliesslich ueber ArchiveCustomerService
 * gesetzt.
 */
class ChangeCustomerServiceStatus
{
    public function __invoke(CustomerService $service, CustomerServiceStatus $status): CustomerService
    {
        if ($service->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Kundenleistungen können nicht mehr verändert werden.'
            );
        }

        if ($status === CustomerServiceStatus::Archived) {
            throw new ReadOnlyRecordException(
                'Die Archivierung erfolgt über die Archivierungsaktion, nicht über den Status.'
            );
        }

        $service->update(['status' => $status]);

        return $service;
    }
}
