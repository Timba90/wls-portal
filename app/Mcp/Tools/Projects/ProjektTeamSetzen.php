<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\SyncProjectMembers;
use App\Mcp\Tools\PortalTool;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('projekt-team-setzen')]
#[Description('Setzt das Team eines Projekts auf die übergebene Liste. Wer nicht in der Liste steht, wird entfernt — eine leere Liste räumt das Team ab. Die Rolle ist Freitext, etwa „Projektleitung".')]
class ProjektTeamSetzen extends PortalTool
{
    public function __construct(private readonly SyncProjectMembers $syncProjectMembers) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'projekt_id' => ['required', 'integer'],
            'mitglieder' => ['present', 'array'],
            'mitglieder.*.benutzer_id' => ['required', 'integer', 'exists:users,id'],
            'mitglieder.*.rolle' => ['nullable', 'string', 'max:255'],
        ]);

        $projekt = Project::query()->find($eingabe['projekt_id']);

        if (! $projekt instanceof Project) {
            return Response::error('Projekt nicht gefunden.');
        }

        $mitglieder = collect($eingabe['mitglieder'])
            ->map(fn (array $mitglied): array => [
                'user_id' => (int) $mitglied['benutzer_id'],
                'role' => $mitglied['rolle'] ?? null,
            ]);

        if ($mitglieder->pluck('user_id')->duplicates()->isNotEmpty()) {
            return Response::error('Eine Person kann nur einmal im Team stehen.');
        }

        ($this->syncProjectMembers)($projekt, $mitglieder->all());

        return Response::json([
            'projekt_id' => $projekt->id,
            'team' => $projekt->load('members.user')->members
                ->map(fn (ProjectMember $mitglied): array => [
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
            'projekt_id' => $schema->integer()->description('Interne ID des Projekts.')->required(),
            'mitglieder' => $schema->array()
                ->items($schema->object([
                    'benutzer_id' => $schema->integer()->description('Interner Benutzer.')->required(),
                    'rolle' => $schema->string()->description('Freitext, etwa „Projektleitung".'),
                ]))
                ->description('Vollständige Teamliste. Eine leere Liste entfernt alle Zuordnungen.')
                ->required(),
        ];
    }
}
