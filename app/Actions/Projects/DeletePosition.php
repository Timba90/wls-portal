<?php

namespace App\Actions\Projects;

use App\Exceptions\ReadOnlyRecordException;
use App\Models\ProjectPosition;

/**
 * Entfernt eine Projektposition.
 */
class DeletePosition
{
    public function __invoke(ProjectPosition $position): void
    {
        if ($position->project->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Projekte können nicht mehr verändert werden.'
            );
        }

        $position->delete();
    }
}
