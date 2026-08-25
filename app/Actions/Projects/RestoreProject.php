<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;

/**
 * Hebt die Archivierung auf.
 *
 * Das Projekt kehrt als pausiert zurueck statt als laufend: ob es tatsaechlich
 * weiterlaeuft, entscheidet die Person, die es reaktiviert.
 */
class RestoreProject
{
    public function __invoke(Project $project): Project
    {
        if ($project->isArchived()) {
            $project->forceFill([
                'status' => ProjectStatus::OnHold,
                'archived_at' => null,
            ])->save();
        }

        return $project;
    }
}
