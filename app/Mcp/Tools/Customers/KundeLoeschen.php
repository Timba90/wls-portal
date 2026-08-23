<?php

namespace App\Mcp\Tools\Customers;

use App\Actions\Maintenance\DeletePermanently;
use App\Mcp\Tools\PortalTool;
use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('kunde-loeschen')]
#[Description('Entfernt einen Kunden endgültig aus der Datenbank, mitsamt seinen Leistungen, Notizen, Dokumenten und Zuordnungen. Der Vorgang ist nicht umkehrbar; für die reversible Variante das Werkzeug kunde-archivieren verwenden.')]
#[IsDestructive]
class KundeLoeschen extends PortalTool
{
    public function __construct(private readonly DeletePermanently $deletePermanently) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'bestaetigung' => ['required', 'string'],
        ]);

        $kunde = Customer::query()->find($eingabe['id']);

        if (! $kunde instanceof Customer) {
            return Response::error('Kunde nicht gefunden.');
        }

        // Die Kundennummer als Bestaetigung verhindert, dass ein falsch
        // aufgeloester Datensatz unbemerkt verschwindet.
        if ($eingabe['bestaetigung'] !== $kunde->customer_number) {
            return Response::error(
                "Bestätigung stimmt nicht. Erwartet wird die Kundennummer „{$kunde->customer_number}\"."
            );
        }

        $nummer = $kunde->customer_number;
        $name = $kunde->displayName();

        $entfernt = ($this->deletePermanently)($kunde);

        return Response::json([
            'entfernt' => true,
            'kundennummer' => $nummer,
            'anzeigename' => $name,
            'mit_entfernt' => $entfernt,
            'hinweis' => 'Die Kundennummer wird nicht erneut vergeben. Die Änderungshistorie bleibt erhalten.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Kunden.')->required(),
            'bestaetigung' => $schema->string()
                ->description('Zur Sicherheit die Kundennummer des betroffenen Kunden, zum Beispiel KD-00001.')
                ->required(),
        ];
    }
}
