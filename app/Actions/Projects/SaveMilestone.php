<?php

namespace App\Actions\Projects;

use App\Exceptions\ReadOnlyRecordException;
use App\Models\Project;
use App\Models\ProjectMilestone;

/**
 * Legt einen Meilenstein an oder aendert ihn.
 */
class SaveMilestone
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Project $project, array $attributes, ?ProjectMilestone $milestone = null): ProjectMilestone
    {
        if ($project->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Projekte können nicht mehr verändert werden.'
            );
        }

        $werte = [
            'name' => $attributes['name'],
            'note' => $attributes['note'] ?? null,
            'status' => $attributes['status'],
            'due_date' => $attributes['due_date'] ?? null,
            'sort_order' => $attributes['sort_order'] ?? $this->nextSortOrder($project, $milestone),
        ];

        if ($milestone) {
            $milestone->update($werte);

            return $milestone;
        }

        return $project->milestones()->create($werte);
    }

    private function nextSortOrder(Project $project, ?ProjectMilestone $milestone): int
    {
        return $milestone?->sort_order ?? ((int) $project->milestones()->max('sort_order') + 1);
    }
}
