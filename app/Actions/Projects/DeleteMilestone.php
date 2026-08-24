<?php

namespace App\Actions\Projects;

use App\Exceptions\ReadOnlyRecordException;
use App\Models\ProjectMilestone;

/**
 * Entfernt einen Meilenstein.
 *
 * Anders als bei Geschaeftsdaten ist das hier ein echtes Loeschen: ein
 * Meilenstein ist Planung, kein Beleg. Der Vorgang steht in der
 * Aenderungshistorie.
 */
class DeleteMilestone
{
    public function __invoke(ProjectMilestone $milestone): void
    {
        if ($milestone->project->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Projekte können nicht mehr verändert werden.'
            );
        }

        $milestone->delete();
    }
}
