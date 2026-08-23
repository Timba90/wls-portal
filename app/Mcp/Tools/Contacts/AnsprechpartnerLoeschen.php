<?php

namespace App\Mcp\Tools\Contacts;

use App\Actions\Maintenance\DeletePermanently;
use App\Mcp\Tools\PortalTool;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('ansprechpartner-loeschen')]
#[Description('Entfernt einen Ansprechpartner endgültig, mitsamt seinen Kundenzuordnungen, Kontaktkanälen, Notizen und Dokumenten. Nicht umkehrbar; die reversible Variante ist ansprechpartner-archivieren.')]
#[IsDestructive]
class AnsprechpartnerLoeschen extends PortalTool
{
    public function __construct(private readonly DeletePermanently $deletePermanently) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'bestaetigung' => ['required', 'string'],
        ]);

        $kontakt = Contact::query()->find($eingabe['id']);

        if (! $kontakt instanceof Contact) {
            return Response::error('Ansprechpartner nicht gefunden.');
        }

        // Der Nachname als Bestaetigung schuetzt vor einem falsch aufgeloesten
        // Datensatz.
        if ($eingabe['bestaetigung'] !== $kontakt->last_name) {
            return Response::error(
                "Bestätigung stimmt nicht. Erwartet wird der Nachname „{$kontakt->last_name}\"."
            );
        }

        $name = $kontakt->fullName();

        $entfernt = ($this->deletePermanently)($kontakt);

        return Response::json([
            'entfernt' => true,
            'name' => $name,
            'mit_entfernt' => $entfernt,
            'hinweis' => 'Die Änderungshistorie bleibt erhalten.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Ansprechpartners.')->required(),
            'bestaetigung' => $schema->string()
                ->description('Zur Sicherheit der Nachname des betroffenen Ansprechpartners.')
                ->required(),
        ];
    }
}
