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
                ...$this->operationsAttributes($project, $attributes),
            ]);

            $project->save();

            return $project;
        });
    }

    /**
     * Die Betriebsfelder folgen nicht dem Muster der uebrigen: wer sie nicht
     * mitsendet, will sie nicht aendern. Ein Aufrufer, der nur den Namen
     * setzt, soll kein Pruefdatum loeschen.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function operationsAttributes(Project $project, array $attributes): array
    {
        $felder = ['backup_status', 'security_status', 'update_status', 'operations_checked_on'];

        $werte = [];

        foreach ($felder as $feld) {
            if (array_key_exists($feld, $attributes)) {
                $werte[$feld] = $attributes[$feld];
            }
        }

        return $werte;
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
