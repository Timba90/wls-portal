<?php

namespace App\Actions\Projects;

use App\Exceptions\ReadOnlyRecordException;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Aendert die Stammdaten eines Projekts.
 *
 * Der Kunde und die Projektnummer bleiben unveraendert: ein Projekt wechselt
 * nicht den Kunden, und die Nummer ist im Model gegen Aenderung geschuetzt.
 */
class UpdateProject
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Project $project, array $attributes): Project
    {
        $this->guardAgainstArchived($project);

        return DB::transaction(function () use ($project, $attributes): Project {
            $project->fill([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'project_type_id' => $attributes['project_type_id'] ?? null,
                'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
                'status' => $attributes['status'] ?? $project->status,
                'start_date' => $attributes['start_date'] ?? null,
                'deadline' => $attributes['deadline'] ?? null,
                'risk_note' => $attributes['risk_note'] ?? null,
            ]);

            $project->save();

            return $project;
        });
    }

    private function guardAgainstArchived(Project $project): void
    {
        if ($project->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Projekte können nicht mehr verändert werden.'
            );
        }
    }
}
