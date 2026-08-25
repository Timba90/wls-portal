<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;

/**
 * Archiviert ein Projekt. Die Daten bleiben vollstaendig erhalten.
 */
class ArchiveProject
{
    public function __invoke(Project $project): Project
    {
        if (! $project->isArchived()) {
            $project->forceFill([
                'status' => ProjectStatus::Archived,
                'archived_at' => now(),
            ])->save();
        }

        return $project;
    }
}
