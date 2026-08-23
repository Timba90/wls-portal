<?php

namespace App\Mcp\Tools\Insights;

use App\Actions\Search\GlobalSearch;
use App\Mcp\Tools\PortalTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('global-suchen')]
#[Description('Sucht mit einem Stichwort gleichzeitig über Kunden, Ansprechpartner, Katalogartikel und Kundenleistungen. Der richtige Einstieg, wenn unklar ist, um welche Art von Datensatz es geht. Berücksichtigt nur aktive Datensätze.')]
#[IsReadOnly]
class GlobalSuchen extends PortalTool
{
    public function __construct(private readonly GlobalSearch $globalSearch) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'suchbegriff' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $gruppen = ($this->globalSearch)($eingabe['suchbegriff']);

        return Response::json([
            'suchbegriff' => $eingabe['suchbegriff'],
            'anzahl_treffer' => $gruppen->sum(fn (array $gruppe): int => $gruppe['treffer']->count()),
            'gruppen' => $gruppen->map(fn (array $gruppe): array => [
                'typ' => $gruppe['typ'],
                'treffer' => $gruppe['treffer']->map(fn (array $treffer): array => [
                    'name' => $treffer['name'],
                    'zusatz' => $treffer['zusatz'],
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()
                ->description('Mindestens zwei Zeichen.')
                ->required(),
        ];
    }
}
