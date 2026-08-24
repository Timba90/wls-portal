<?php

namespace App\Mcp\Tools\Projects;

use App\Mcp\Tools\PortalTool;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectPosition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('projekt-lesen')]
#[Description('Liefert ein Projekt vollständig: Stammdaten, Meilensteine mit Terminen, Positionen mit Herkunft und Beträgen, Team sowie den daraus errechneten Fortschritt und das Projektvolumen.')]
#[IsReadOnly]
class ProjektLesen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate(['id' => ['required', 'integer']]);

        $projekt = Project::query()
            ->with([
                'customer',
                'projectType',
                'responsibleUser',
                'milestones',
                'positions.product',
                'positions.customerService',
                'members.user',
            ])
            ->find($eingabe['id']);

        if (! $projekt instanceof Project) {
            return Response::error('Projekt nicht gefunden.');
        }

        return Response::json([
            'id' => $projekt->id,
            'projektnummer' => $projekt->project_number,
            'name' => $projekt->name,
            'beschreibung' => $projekt->description,
            'kunde' => $projekt->customer->only(['id', 'customer_number']) + [
                'name' => $projekt->customer->displayName(),
            ],
            'projekttyp' => $projekt->projectType?->only(['id', 'name']),
            'verantwortlich' => $projekt->responsibleUser?->only(['id', 'name']),
            'status' => $projekt->status->value,
            'status_bezeichnung' => $projekt->status->label(),
            'beginn' => $this->date($projekt->start_date),
            'deadline' => $this->date($projekt->deadline),
            'tage_bis_deadline' => $projekt->daysUntilDeadline(),
            'ueberfaellig' => $projekt->isOverdue(),
            'risiko' => $projekt->risk_note,
            'archiviert_am' => $this->dateTime($projekt->archived_at),

            // `null`, wenn es keine Meilensteine gibt — dann fehlt dem
            // Prozentwert die Grundlage.
            'fortschritt_prozent' => $projekt->progressPercentage(),
            'volumen_einmalig' => $this->money($projekt->oneTimeVolume()->cents),
            'volumen_wiederkehrend' => $this->money($projekt->recurringVolume()->cents),

            'meilensteine' => $projekt->milestones->map(fn (ProjectMilestone $meilenstein): array => [
                'id' => $meilenstein->id,
                'name' => $meilenstein->name,
                'notiz' => $meilenstein->note,
                'status' => $meilenstein->status->value,
                'termin' => $this->date($meilenstein->due_date),
                'tage_bis_termin' => $meilenstein->daysUntilDue(),
                'ueberfaellig' => $meilenstein->isOverdue(),
                'zaehlt_als_erledigt' => $meilenstein->status->countsAsSettled(),
            ])->all(),

            'positionen' => $projekt->positions->map(fn (ProjectPosition $position): array => [
                'id' => $position->id,
                'name' => $position->name,
                'herkunft' => $position->source(),
                'produkt_id' => $position->product_id,
                'kundenleistung_id' => $position->customer_service_id,
                'art' => $position->kind->value,
                'menge' => (float) $position->quantity,
                'einzelpreis' => $this->money($position->unit_price_cents),
                'gesamt' => $this->money($position->total()->cents),
                'status' => $position->status->value,
            ])->all(),

            'team' => $projekt->members->map(fn (ProjectMember $mitglied): array => [
                'id' => $mitglied->id,
                'benutzer_id' => $mitglied->user_id,
                'name' => $mitglied->user->name,
                'rolle' => $mitglied->role,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Projekts.')->required(),
        ];
    }
}
