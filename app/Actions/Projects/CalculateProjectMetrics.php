<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Support\Money;

/**
 * Kennzahlen fuer den Kopf der Projektliste.
 *
 * Bewusst nur Zahlen mit Datengrundlage: offene Projekte, ueberfaellige
 * Projekte, das Volumen der offenen Projekte und die Meilensteine der
 * naechsten zwei Wochen.
 */
class CalculateProjectMetrics
{
    /**
     * @return array{
     *     open: int,
     *     overdue: int,
     *     volume: Money,
     *     dueSoon: int,
     * }
     */
    public function __invoke(): array
    {
        $offene = Project::query()
            ->with('positions')
            ->whereIn('status', array_column(
                array_filter(ProjectStatus::cases(), fn (ProjectStatus $status): bool => $status->isOpen()),
                'value',
            ))
            ->get();

        $volumen = $offene->reduce(
            fn (Money $summe, Project $projekt): Money => $summe->plus($projekt->oneTimeVolume()),
            Money::zero(),
        );

        return [
            'open' => $offene->count(),
            'overdue' => $offene->filter(fn (Project $projekt): bool => $projekt->isOverdue())->count(),
            'volume' => $volumen,
            'dueSoon' => ProjectMilestone::query()
                ->open()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', now()->toDateString())
                ->whereDate('due_date', '<=', now()->addDays(14)->toDateString())
                ->whereHas('project', fn ($query) => $query->active())
                ->count(),
        ];
    }
}
