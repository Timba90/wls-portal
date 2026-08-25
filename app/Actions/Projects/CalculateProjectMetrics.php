<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

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
        $offene = Project::query()->with('positions')->open()->get();

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
                ->whereHas('project', fn (Builder $query) => $query->open())
                ->count(),
        ];
    }
}
