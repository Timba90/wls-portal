<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\UpdateProject;
use App\Exceptions\ReadOnlyRecordException;
use App\Mcp\Tools\PortalTool;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('projekt-speichern')]
#[Description('Legt ein Projekt an oder ändert es. Mit „kunde_id" wird angelegt, mit „id" geändert. Die Projektnummer vergibt das System und bleibt danach unveränderlich; der Kunde wechselt nicht. Archivierte Projekte sind schreibgeschützt.')]
class ProjektSpeichern extends PortalTool
{
    public function __construct(
        private readonly CreateProject $createProject,
        private readonly UpdateProject $updateProject,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'kunde_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:5000'],
            'projekttyp_id' => ['nullable', 'integer', 'exists:project_types,id'],
            'verantwortlich_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:planned,active,on_hold,completed,cancelled'],
            'beginn' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:beginn'],
            'risiko' => ['nullable', 'string', 'max:2000'],
        ]);

        $attribute = [
            'name' => $eingabe['name'],
            'description' => $eingabe['beschreibung'] ?? null,
            'project_type_id' => $eingabe['projekttyp_id'] ?? null,
            'responsible_user_id' => $eingabe['verantwortlich_id'] ?? null,
            'status' => $eingabe['status'] ?? null,
            'start_date' => $eingabe['beginn'] ?? null,
            'deadline' => $eingabe['deadline'] ?? null,
            'risk_note' => $eingabe['risiko'] ?? null,
        ];

        if (filled($eingabe['id'] ?? null)) {
            $projekt = Project::query()->find($eingabe['id']);

            if (! $projekt instanceof Project) {
                return Response::error('Projekt nicht gefunden.');
            }

            try {
                // Ohne Statusangabe bleibt der bisherige stehen.
                $projekt = ($this->updateProject)($projekt, array_filter(
                    $attribute,
                    fn (mixed $wert, string $schluessel): bool => $schluessel !== 'status' || ! is_null($wert),
                    ARRAY_FILTER_USE_BOTH,
                ));
            } catch (ReadOnlyRecordException $ausnahme) {
                return Response::error($ausnahme->getMessage());
            }

            return $this->antwort($projekt, 'geändert');
        }

        if (blank($eingabe['kunde_id'] ?? null)) {
            return Response::error('Zum Anlegen wird „kunde_id" benötigt, zum Ändern „id".');
        }

        $kunde = Customer::query()->find($eingabe['kunde_id']);

        if (! $kunde instanceof Customer) {
            return Response::error('Kunde nicht gefunden.');
        }

        return $this->antwort(($this->createProject)($kunde, $attribute), 'angelegt');
    }

    private function antwort(Project $projekt, string $vorgang): Response
    {
        return Response::json([
            'vorgang' => $vorgang,
            'id' => $projekt->id,
            'projektnummer' => $projekt->project_number,
            'name' => $projekt->name,
            'status' => $projekt->status->value,
            'kunde_id' => $projekt->customer_id,
            'deadline' => $this->date($projekt->deadline),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID zum Ändern eines bestehenden Projekts.'),
            'kunde_id' => $schema->integer()->description('Kunde des neuen Projekts. Nur beim Anlegen; ein Projekt wechselt nicht den Kunden.'),
            'name' => $schema->string()->description('Projektname.')->required(),
            'beschreibung' => $schema->string()->description('Freitext zum Projektinhalt.'),
            'projekttyp_id' => $schema->integer()->description('ID eines Projekttyps; die Liste ist frei definierbar.'),
            'verantwortlich_id' => $schema->integer()->description('Interner Verantwortlicher.'),
            'status' => $schema->string()->description('planned, active, on_hold, completed oder cancelled. Archiviert entsteht nur über „projekt-archivieren".'),
            'beginn' => $schema->string()->description('Beginn als JJJJ-MM-TT.'),
            'deadline' => $schema->string()->description('Deadline als JJJJ-MM-TT, nicht vor dem Beginn.'),
            'risiko' => $schema->string()->description('Woran das Projekt scheitern könnte.'),
        ];
    }
}
