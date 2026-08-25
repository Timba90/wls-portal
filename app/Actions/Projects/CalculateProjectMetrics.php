<?php

namespace App\Actions\Projects;

use App\Enums\OperationsStatus;
use App\Models\Project;
use App\Support\Money;

/**
 * Kennzahlen fuer den Kopf der Projektliste.
 *
 * Die Uebersicht beantwortet zwei Fragen: was bringt der Bestand, und wo
 * stimmt der Betrieb nicht. Termine und Deadlines gehoeren in das jeweilige
 * Projekt, nicht in den Kopf der Liste.
 */
class CalculateProjectMetrics
{
    /**
     * @return array{
     *     open: int,
     *     revenue: Money,
     *     monthlyRevenue: Money,
     *     needsAttention: int,
     * }
     */
    public function __invoke(): array
    {
        $offene = Project::query()->with('positions')->open()->get();

        return [
            'open' => $offene->count(),
            'revenue' => $offene->reduce(
                fn (Money $summe, Project $projekt): Money => $summe->plus($projekt->oneTimeVolume()),
                Money::zero(),
            ),
            'monthlyRevenue' => $offene->reduce(
                fn (Money $summe, Project $projekt): Money => $summe->plus($projekt->recurringVolume()),
                Money::zero(),
            ),
            'needsAttention' => $offene
                ->filter(fn (Project $projekt): bool => collect($projekt->operationsStatuses())
                    ->contains(fn (OperationsStatus $status): bool => ! $status->isSettled()))
                ->count(),
        ];
    }
}
