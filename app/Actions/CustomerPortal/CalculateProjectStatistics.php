<?php

namespace App\Actions\CustomerPortal;

use App\Enums\MilestoneStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Kennzahlen zu den Projekten eines Kunden fuer das Kundenportal.
 *
 * Bewusst ohne Geld: Einkaufspreise, Margen und interne Kalkulation bleiben
 * intern. Der Kunde sieht Fortschritt, Termine, Betriebsstatus und den Stand
 * seiner Tickets je Projekt.
 */
class CalculateProjectStatistics
{
    /**
     * @return array{
     *     open: int,
     *     completed: int,
     *     averageProgress: ?int,
     *     milestonesDone: int,
     *     milestonesTotal: int,
     *     upcomingMilestones: Collection<int, ProjectMilestone>,
     *     overdueMilestones: int,
     * }
     */
    public function forCustomer(Customer $customer): array
    {
        $projekte = Project::query()
            ->whereBelongsTo($customer)
            ->notArchived()
            ->with('milestones')
            ->get();

        $offene = $projekte->filter(fn (Project $projekt): bool => $projekt->status->isOpen());

        $fortschritte = $offene
            ->map(fn (Project $projekt): ?int => $projekt->progressPercentage())
            ->filter(fn (?int $wert): bool => $wert !== null);

        $meilensteine = $offene->flatMap(fn (Project $projekt): Collection => $projekt->milestones);

        return [
            'open' => $offene->count(),
            'completed' => $projekte->count() - $offene->count(),
            'averageProgress' => $fortschritte->isEmpty() ? null : (int) round($fortschritte->avg()),
            'milestonesDone' => $meilensteine->filter(fn (ProjectMilestone $meilenstein): bool => $meilenstein->status->countsAsSettled())->count(),
            'milestonesTotal' => $meilensteine->count(),
            'upcomingMilestones' => $this->upcoming($offene),
            'overdueMilestones' => $meilensteine->filter(fn (ProjectMilestone $meilenstein): bool => $meilenstein->isOverdue())->count(),
        ];
    }

    /**
     * Kennzahlen eines einzelnen Projekts.
     *
     * @return array{
     *     progress: ?int,
     *     milestonesByStatus: array<string, int>,
     *     milestonesDone: int,
     *     milestonesTotal: int,
     *     nextMilestone: ?ProjectMilestone,
     *     daysUntilDeadline: ?int,
     *     daysRunning: ?int,
     *     ticketsOpen: int,
     *     ticketsTotal: int,
     * }
     */
    public function forProject(Project $project): array
    {
        $project->loadMissing('milestones');

        $nachStatus = collect(MilestoneStatus::cases())
            ->mapWithKeys(fn (MilestoneStatus $status): array => [
                $status->value => $project->milestones->where('status', $status)->count(),
            ])
            ->all();

        return [
            'progress' => $project->progressPercentage(),
            'milestonesByStatus' => $nachStatus,
            'milestonesDone' => $project->milestones->filter(fn (ProjectMilestone $meilenstein): bool => $meilenstein->status->countsAsSettled())->count(),
            'milestonesTotal' => $project->milestones->count(),
            'nextMilestone' => $this->upcoming(collect([$project]))->first(),
            'daysUntilDeadline' => $project->daysUntilDeadline(),
            'daysRunning' => $project->start_date === null
                ? null
                : max(0, (int) $project->start_date->startOfDay()->diffInDays(now()->startOfDay())),
            'ticketsOpen' => Ticket::query()->whereBelongsTo($project)->open()->count(),
            'ticketsTotal' => Ticket::query()->whereBelongsTo($project)->count(),
        ];
    }

    /**
     * Die naechsten offenen Meilensteine mit Termin, frueheste zuerst.
     *
     * @param  Collection<int, Project>  $projekte
     * @return Collection<int, ProjectMilestone>
     */
    private function upcoming(Collection $projekte): Collection
    {
        return $projekte
            ->flatMap(fn (Project $projekt): Collection => $projekt->milestones->each(
                fn (ProjectMilestone $meilenstein) => $meilenstein->setRelation('project', $projekt),
            ))
            ->filter(fn (ProjectMilestone $meilenstein): bool => ! $meilenstein->status->countsAsSettled() && $meilenstein->due_date !== null)
            ->sortBy(fn (ProjectMilestone $meilenstein): string => $meilenstein->due_date->toDateString())
            ->take(5)
            ->values();
    }
}
