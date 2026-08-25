<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Maintenance\DeletePermanently;
use App\Mcp\Tools\PortalTool;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('projekt-loeschen')]
#[Description('Entfernt ein Projekt endgültig, mitsamt Meilensteinen, Positionen und Teamzuordnungen. Kunde und Kundenleistungen bleiben unberührt. Nicht umkehrbar — der übliche Weg ist „projekt-archivieren".')]
#[IsDestructive]
class ProjektLoeschen extends PortalTool
{
    public function __construct(private readonly DeletePermanently $deletePermanently) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'bestaetigung' => ['required', 'string'],
        ]);

        $projekt = Project::query()->withCount(['milestones', 'positions', 'members'])->find($eingabe['id']);

        if (! $projekt instanceof Project) {
            return Response::error('Projekt nicht gefunden.');
        }

        if ($eingabe['bestaetigung'] !== $projekt->project_number) {
            return Response::error(
                "Bestätigung stimmt nicht. Erwartet wird die Projektnummer „{$projekt->project_number}\"."
            );
        }

        $zusammenfassung = [
            'projektnummer' => $projekt->project_number,
            'name' => $projekt->name,
            'meilensteine' => $projekt->milestones_count,
            'positionen' => $projekt->positions_count,
            'teammitglieder' => $projekt->members_count,
        ];

        $entfernt = ($this->deletePermanently)($projekt);

        return Response::json([
            'entfernt' => true,
            ...$zusammenfassung,
            'mit_entfernt' => $entfernt,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Projekts.')->required(),
            'bestaetigung' => $schema->string()->description('Zur Sicherheit die Projektnummer, etwa PR-00001.')->required(),
        ];
    }
}
