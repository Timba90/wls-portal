<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\UpdateProject;
use App\Exceptions\ReadOnlyRecordException;
use App\Mcp\Tools\PortalTool;
use App\Models\Customer;
use App\Models\Project;
use Carbon\CarbonImmutable as Carbon;
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

        if (filled($eingabe['id'] ?? null)) {
            return $this->update($eingabe);
        }

        if (blank($eingabe['kunde_id'] ?? null)) {
            return Response::error('Zum Anlegen wird „kunde_id" benötigt, zum Ändern „id".');
        }

        $kunde = Customer::query()->find($eingabe['kunde_id']);

        if (! $kunde instanceof Customer) {
            return Response::error('Kunde nicht gefunden.');
        }

        return $this->antwort(($this->createProject)($kunde, $this->attribute($eingabe)), 'angelegt');
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function update(array $eingabe): Response
    {
        $projekt = Project::query()->find($eingabe['id']);

        if (! $projekt instanceof Project) {
            return Response::error('Projekt nicht gefunden.');
        }

        // Fehlende Felder aus dem Bestand ergaenzen, damit ein Aufruf mit nur
        // einem geaenderten Feld nicht den Rest leert — dieselbe Regel wie bei
        // „kunde-speichern". Die Actions schreiben immer alle Felder; ein
        // nicht gesendetes Feld waere sonst stillschweigend eine Loeschung.
        $eingabe['beschreibung'] ??= $projekt->description;
        $eingabe['projekttyp_id'] ??= $projekt->project_type_id;
        $eingabe['verantwortlich_id'] ??= $projekt->responsible_user_id;
        $eingabe['status'] ??= $projekt->status->value;
        $eingabe['beginn'] ??= $this->date($projekt->start_date);
        $eingabe['deadline'] ??= $this->date($projekt->deadline);
        $eingabe['risiko'] ??= $projekt->risk_note;

        // Die Regel „deadline nicht vor beginn" greift bei der Validierung nur,
        // wenn beide Felder gesendet wurden. Nach dem Ergaenzen aus dem Bestand
        // koennen sie sich trotzdem widersprechen.
        // Nicht als Zeichenketten vergleichen: die Validierung laesst jedes von
        // `date` akzeptierte Format zu, nicht nur JJJJ-MM-TT.
        if (filled($eingabe['beginn']) && filled($eingabe['deadline'])
            && Carbon::parse($eingabe['deadline'])->lt(Carbon::parse($eingabe['beginn']))) {
            return Response::error('Die Deadline darf nicht vor dem Beginn liegen.');
        }

        try {
            $projekt = ($this->updateProject)($projekt, $this->attribute($eingabe));
        } catch (ReadOnlyRecordException $ausnahme) {
            return Response::error($ausnahme->getMessage());
        }

        return $this->antwort($projekt, 'geändert');
    }

    /**
     * Uebersetzt die deutschen Feldnamen des Werkzeugs in die Struktur der Action.
     *
     * @param  array<string, mixed>  $eingabe
     * @return array<string, mixed>
     */
    private function attribute(array $eingabe): array
    {
        return [
            'name' => $eingabe['name'],
            'description' => $eingabe['beschreibung'] ?? null,
            'project_type_id' => $eingabe['projekttyp_id'] ?? null,
            'responsible_user_id' => $eingabe['verantwortlich_id'] ?? null,
            'status' => $eingabe['status'] ?? null,
            'start_date' => $eingabe['beginn'] ?? null,
            'deadline' => $eingabe['deadline'] ?? null,
            'risk_note' => $eingabe['risiko'] ?? null,
        ];
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
