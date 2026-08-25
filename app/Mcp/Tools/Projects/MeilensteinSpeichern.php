<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\DeleteMilestone;
use App\Actions\Projects\SaveMilestone;
use App\Exceptions\ReadOnlyRecordException;
use App\Mcp\Tools\PortalTool;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('meilenstein-speichern')]
#[Description('Legt einen Meilenstein an, ändert ihn oder entfernt ihn. Meilensteine tragen den Fortschritt des Projekts; entfallene zählen dabei als erledigt. Löschen ist hier endgültig, weil ein Meilenstein Planung ist und kein Beleg.')]
class MeilensteinSpeichern extends PortalTool
{
    public function __construct(
        private readonly SaveMilestone $saveMilestone,
        private readonly DeleteMilestone $deleteMilestone,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'projekt_id' => ['required', 'integer'],
            'id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'notiz' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:open,in_progress,done,skipped'],
            'termin' => ['nullable', 'date'],
            'sortierung' => ['nullable', 'integer', 'min:0'],
            'entfernen' => ['nullable', 'boolean'],
        ]);

        $projekt = Project::query()->find($eingabe['projekt_id']);

        if (! $projekt instanceof Project) {
            return Response::error('Projekt nicht gefunden.');
        }

        $meilenstein = filled($eingabe['id'] ?? null)
            ? $projekt->milestones()->find($eingabe['id'])
            : null;

        if (filled($eingabe['id'] ?? null) && ! $meilenstein instanceof ProjectMilestone) {
            return Response::error('Meilenstein gehört nicht zu diesem Projekt.');
        }

        try {
            if ($eingabe['entfernen'] ?? false) {
                if (! $meilenstein instanceof ProjectMilestone) {
                    return Response::error('Zum Entfernen wird „id" benötigt.');
                }

                ($this->deleteMilestone)($meilenstein);

                return $this->antwort($projekt->fresh('milestones'), 'entfernt', null);
            }

            $name = $eingabe['name'] ?? $meilenstein?->name;

            if (blank($name)) {
                return Response::error('Zum Anlegen wird „name" benötigt.');
            }

            // Nicht uebergebene Felder behalten ihren bisherigen Wert; die
            // Action ersetzt den Datensatz vollstaendig.
            $gespeichert = ($this->saveMilestone)($projekt, [
                'name' => $name,
                'note' => $eingabe['notiz'] ?? $meilenstein?->note,
                'status' => $eingabe['status'] ?? $meilenstein?->status->value ?? 'open',
                'due_date' => $eingabe['termin'] ?? $this->date($meilenstein?->due_date),
                'sort_order' => $eingabe['sortierung'] ?? $meilenstein?->sort_order,
            ], $meilenstein);
        } catch (ReadOnlyRecordException $ausnahme) {
            return Response::error($ausnahme->getMessage());
        }

        return $this->antwort($projekt->fresh('milestones'), $meilenstein ? 'geändert' : 'angelegt', $gespeichert);
    }

    private function antwort(Project $projekt, string $vorgang, ?ProjectMilestone $meilenstein): Response
    {
        return Response::json([
            'vorgang' => $vorgang,
            'projekt_id' => $projekt->id,
            'meilenstein' => $meilenstein === null ? null : [
                'id' => $meilenstein->id,
                'name' => $meilenstein->name,
                'status' => $meilenstein->status->value,
                'termin' => $this->date($meilenstein->due_date),
            ],
            'anzahl_meilensteine' => $projekt->milestones->count(),
            'fortschritt_prozent' => $projekt->progressPercentage(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'projekt_id' => $schema->integer()->description('Projekt, zu dem der Meilenstein gehört.')->required(),
            'id' => $schema->integer()->description('Interne ID zum Ändern oder Entfernen.'),
            'name' => $schema->string()->description('Bezeichnung des Meilensteins.'),
            'notiz' => $schema->string()->description('Kurze Ergänzung.'),
            'status' => $schema->string()->description('open, in_progress, done oder skipped. Done und skipped zählen als erledigt.'),
            'termin' => $schema->string()->description('Termin als JJJJ-MM-TT.'),
            'sortierung' => $schema->integer()->description('Position in der Liste.'),
            'entfernen' => $schema->boolean()->description('true entfernt den Meilenstein endgültig.'),
        ];
    }
}
