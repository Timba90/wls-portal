<?php

namespace App\Mcp\Tools\Services;

use App\Actions\Maintenance\DeletePermanently;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('leistung-loeschen')]
#[Description('Entfernt eine Kundenleistung endgültig, mitsamt ihrem gesamten Preisverlauf, ihren Notizen und Dokumenten. Nicht umkehrbar; die reversible Variante ist der Status „archiviert" über leistung-status-setzen.')]
#[IsDestructive]
class LeistungLoeschen extends PortalTool
{
    public function __construct(private readonly DeletePermanently $deletePermanently) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'bestaetigung' => ['required', 'string'],
        ]);

        $leistung = CustomerService::query()->with('customer')->find($eingabe['id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        if ($eingabe['bestaetigung'] !== $leistung->name) {
            return Response::error(
                "Bestätigung stimmt nicht. Erwartet wird der Name der Leistung „{$leistung->name}\"."
            );
        }

        $name = $leistung->name;
        $kunde = $leistung->customer->displayName();
        $preisaenderungen = $leistung->priceChanges()->count();

        $entfernt = ($this->deletePermanently)($leistung);
        $entfernt['preisaenderungen'] = $preisaenderungen;

        return Response::json([
            'entfernt' => true,
            'name' => $name,
            'kunde' => $kunde,
            'mit_entfernt' => array_filter($entfernt, fn (int $anzahl): bool => $anzahl > 0),
            'hinweis' => 'Der Preisverlauf dieser Leistung ist damit unwiederbringlich weg. Die Änderungshistorie bleibt erhalten.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID der Kundenleistung.')->required(),
            'bestaetigung' => $schema->string()
                ->description('Zur Sicherheit der Name der betroffenen Leistung.')
                ->required(),
        ];
    }
}
