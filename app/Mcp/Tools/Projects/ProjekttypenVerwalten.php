<?php

namespace App\Mcp\Tools\Projects;

use App\Mcp\Tools\PortalTool;
use App\Models\ProjectType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('projekttypen-verwalten')]
#[Description('Listet die frei definierbaren Projekttypen oder legt einen an beziehungsweise ändert ihn. Ohne Angaben wird nur gelistet. Webseite, Shop, Web-App und API sind Beispiele, keine feste Liste.')]
class ProjekttypenVerwalten extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:60'],
            'kuerzel' => ['nullable', 'string', 'max:12'],
            'farbe' => ['nullable', 'string', 'in:gray,blue,green,amber,red,purple'],
            'sortierung' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktiv' => ['nullable', 'boolean'],
        ]);

        if (blank($eingabe['name'] ?? null) && blank($eingabe['id'] ?? null)) {
            return $this->liste();
        }

        $typ = filled($eingabe['id'] ?? null) ? ProjectType::query()->find($eingabe['id']) : null;

        if (filled($eingabe['id'] ?? null) && ! $typ instanceof ProjectType) {
            return Response::error('Projekttyp nicht gefunden.');
        }

        $name = $eingabe['name'] ?? $typ?->name;

        if (blank($name)) {
            return Response::error('Zum Anlegen wird „name" benötigt.');
        }

        $doppelt = ProjectType::query()
            ->where('name', $name)
            ->when($typ, fn ($query) => $query->whereKeyNot($typ->getKey()))
            ->exists();

        if ($doppelt) {
            return Response::error("Es gibt bereits einen Projekttyp „{$name}\".");
        }

        $werte = [
            'name' => $name,
            'short_label' => $eingabe['kuerzel'] ?? $typ?->short_label,
            'color' => $eingabe['farbe'] ?? $typ?->color ?? 'gray',
            'sort_order' => $eingabe['sortierung'] ?? $typ?->sort_order ?? 0,
            'is_active' => $eingabe['aktiv'] ?? $typ?->is_active ?? true,
        ];

        $typ = $typ
            ? tap($typ)->update($werte)
            : ProjectType::query()->create($werte);

        return Response::json([
            'vorgang' => filled($eingabe['id'] ?? null) ? 'geändert' : 'angelegt',
            'projekttyp' => $this->darstellung($typ),
        ]);
    }

    private function liste(): Response
    {
        $typen = ProjectType::query()->withCount('projects')->orderBy('sort_order')->orderBy('name')->get();

        return Response::json([
            'anzahl' => $typen->count(),
            'projekttypen' => $typen->map(fn (ProjectType $typ): array => $this->darstellung($typ))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function darstellung(ProjectType $typ): array
    {
        return [
            'id' => $typ->id,
            'name' => $typ->name,
            'kuerzel' => $typ->badge(),
            'farbe' => $typ->color,
            'sortierung' => $typ->sort_order,
            'aktiv' => $typ->is_active,
            'anzahl_projekte' => $typ->projects_count ?? $typ->projects()->count(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID zum Ändern eines bestehenden Typs.'),
            'name' => $schema->string()->description('Name des Projekttyps. Ohne Angabe und ohne „id" wird nur gelistet.'),
            'kuerzel' => $schema->string()->description('Kurzform für die Anzeige, höchstens 12 Zeichen.'),
            'farbe' => $schema->string()->description('gray, blue, green, amber, red oder purple.'),
            'sortierung' => $schema->integer()->description('Position in der Liste.'),
            'aktiv' => $schema->boolean()->description('Inaktive Typen stehen bei neuen Projekten nicht zur Auswahl.'),
        ];
    }
}
