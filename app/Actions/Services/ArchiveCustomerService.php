<?php

namespace App\Actions\Services;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;

/**
 * Archiviert eine Kundenleistung.
 *
 * Danach ist sie vollstaendig schreibgeschuetzt und bleibt historisch erhalten.
 */
class ArchiveCustomerService
{
    public function __invoke(CustomerService $service): CustomerService
    {
        if ($service->isArchived()) {
            return $service;
        }

        $service->forceFill([
            'status' => CustomerServiceStatus::Archived,
            'archived_at' => now(),
        ])->save();

        return $service;
    }
}
