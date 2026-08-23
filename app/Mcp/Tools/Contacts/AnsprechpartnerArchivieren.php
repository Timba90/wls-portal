<?php

namespace App\Mcp\Tools\Contacts;

use App\Actions\Contacts\ArchiveContact;
use App\Actions\Contacts\RestoreContact;
use App\Mcp\Tools\PortalTool;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('ansprechpartner-archivieren')]
#[Description('Archiviert einen Ansprechpartner oder hebt die Archivierung auf. Die Kundenzuordnungen bleiben erhalten.')]
#[IsIdempotent]
class AnsprechpartnerArchivieren extends PortalTool
{
    public function __construct(
        private readonly ArchiveContact $archiveContact,
        private readonly RestoreContact $restoreContact,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'archivieren' => ['nullable', 'boolean'],
        ]);

        $kontakt = Contact::query()->find($eingabe['id']);

        if (! $kontakt instanceof Contact) {
            return Response::error('Ansprechpartner nicht gefunden.');
        }

        $kontakt = ($eingabe['archivieren'] ?? true)
            ? ($this->archiveContact)($kontakt)
            : ($this->restoreContact)($kontakt);

        return Response::json([
            'id' => $kontakt->id,
            'name' => $kontakt->fullName(),
            'archiviert' => $kontakt->isArchived(),
            'archiviert_am' => $this->dateTime($kontakt->archived_at),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Ansprechpartners.')->required(),
            'archivieren' => $schema->boolean()
                ->description('true archiviert, false hebt die Archivierung auf. Standard ist true.'),
        ];
    }
}
