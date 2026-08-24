<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\RestoreProject;
use App\Mcp\Tools\PortalTool;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('projekt-archivieren')]
#[Description('Archiviert ein Projekt oder hebt die Archivierung wieder auf. Archivierte Projekte behalten alle Daten und sind schreibgeschützt. Beim Reaktivieren kehrt das Projekt als pausiert zurück, nicht als laufend.')]
class ProjektArchivieren extends PortalTool
{
    public function __construct(
        private readonly ArchiveProject $archiveProject,
        private readonly RestoreProject $restoreProject,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'reaktivieren' => ['nullable', 'boolean'],
        ]);

        $projekt = Project::query()->find($eingabe['id']);

        if (! $projekt instanceof Project) {
            return Response::error('Projekt nicht gefunden.');
        }

        $reaktivieren = $eingabe['reaktivieren'] ?? false;

        $projekt = $reaktivieren
            ? ($this->restoreProject)($projekt)
            : ($this->archiveProject)($projekt);

        return Response::json([
            'id' => $projekt->id,
            'projektnummer' => $projekt->project_number,
            'archiviert' => $projekt->isArchived(),
            'status' => $projekt->status->value,
            'archiviert_am' => $this->dateTime($projekt->archived_at),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Projekts.')->required(),
            'reaktivieren' => $schema->boolean()->description('true hebt die Archivierung auf; das Projekt kehrt als pausiert zurück.'),
        ];
    }
}
