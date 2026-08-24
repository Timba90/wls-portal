<?php

namespace App\Mcp\Tools\Projects;

use App\Enums\ProjectStatus;
use App\Mcp\Tools\PortalTool;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('projekte-suchen')]
#[Description('Sucht Projekte nach Nummer oder Name und filtert nach Status, Kunde, Projekttyp und Verantwortlichem. Liefert je Projekt Fortschritt, Volumen und Deadline. Ohne Statusangabe werden nur offene Projekte gezeigt.')]
#[IsReadOnly]
class ProjekteSuchen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'suche' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:planned,active,on_hold,completed,cancelled,archived,offen,alle'],
            'kunde_id' => ['nullable', 'integer'],
            'projekttyp_id' => ['nullable', 'integer'],
            'verantwortlich_id' => ['nullable', 'integer'],
            'nur_ueberfaellig' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer'],
        ]);

        $status = $eingabe['status'] ?? 'offen';

        $query = Project::query()
            ->with(['customer', 'projectType', 'responsibleUser', 'milestones', 'positions'])
            ->when($status === 'offen', fn (Builder $q) => $q->whereIn('status', $this->openStatusValues()))
            ->when(
                ! in_array($status, ['offen', 'alle'], true),
                fn (Builder $q) => $q->where('status', $status),
            )
            ->when(filled($eingabe['kunde_id'] ?? null), fn (Builder $q) => $q->where('customer_id', $eingabe['kunde_id']))
            ->when(filled($eingabe['projekttyp_id'] ?? null), fn (Builder $q) => $q->where('project_type_id', $eingabe['projekttyp_id']))
            ->when(filled($eingabe['verantwortlich_id'] ?? null), fn (Builder $q) => $q->where('responsible_user_id', $eingabe['verantwortlich_id']))
            ->orderByRaw('deadline is null')
            ->orderBy('deadline');

        $this->applySearch($query, $eingabe['suche'] ?? null, ['project_number', 'name']);

        $projekte = $query->limit($this->limit($eingabe['limit'] ?? null))->get();

        if ($eingabe['nur_ueberfaellig'] ?? false) {
            $projekte = $projekte->filter(fn (Project $projekt): bool => $projekt->isOverdue())->values();
        }

        return Response::json([
            'anzahl' => $projekte->count(),
            'projekte' => $projekte->map(fn (Project $projekt): array => [
                'id' => $projekt->id,
                'projektnummer' => $projekt->project_number,
                'name' => $projekt->name,
                'kunde' => $projekt->customer->only(['id', 'customer_number']) + [
                    'name' => $projekt->customer->displayName(),
                ],
                'projekttyp' => $projekt->projectType?->only(['id', 'name']),
                'verantwortlich' => $projekt->responsibleUser?->only(['id', 'name']),
                'status' => $projekt->status->value,
                'fortschritt_prozent' => $projekt->progressPercentage(),
                'volumen_einmalig' => $this->money($projekt->oneTimeVolume()->cents),
                'volumen_wiederkehrend' => $this->money($projekt->recurringVolume()->cents),
                'beginn' => $this->date($projekt->start_date),
                'deadline' => $this->date($projekt->deadline),
                'tage_bis_deadline' => $projekt->daysUntilDeadline(),
                'ueberfaellig' => $projekt->isOverdue(),
            ])->all(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function openStatusValues(): array
    {
        return array_values(array_map(
            fn (ProjectStatus $status): string => $status->value,
            array_filter(ProjectStatus::cases(), fn (ProjectStatus $status): bool => $status->isOpen()),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suche' => $schema->string()->description('Stichwort für Projektnummer oder Name.'),
            'status' => $schema->string()->description('planned, active, on_hold, completed, cancelled, archived — oder „offen" (Voreinstellung) beziehungsweise „alle".'),
            'kunde_id' => $schema->integer()->description('Nur Projekte dieses Kunden.'),
            'projekttyp_id' => $schema->integer()->description('Nur Projekte dieses Typs.'),
            'verantwortlich_id' => $schema->integer()->description('Nur Projekte dieses internen Verantwortlichen.'),
            'nur_ueberfaellig' => $schema->boolean()->description('Nur offene Projekte mit verstrichener Deadline.'),
            'limit' => $schema->integer()->description('Höchstzahl der Treffer, Standard 25, Maximum 100.'),
        ];
    }
}
