<?php

namespace App\Mcp\Tools\Contacts;

use App\Mcp\Tools\PortalTool;
use App\Models\Contact;
use App\Models\ContactAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ansprechpartner-suchen')]
#[Description('Durchsucht die Ansprechpartner nach Namen oder E-Mail-Adresse und lässt sich auf einen Kunden einschränken. Ein Ansprechpartner kann bei mehreren Kunden geführt sein.')]
#[IsReadOnly]
class AnsprechpartnerSuchen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'suchbegriff' => ['nullable', 'string', 'max:255'],
            'kunde_id' => ['nullable', 'integer'],
            'nur_aktive' => ['nullable', 'boolean'],
            'anzahl' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Contact::query()->with(['emailAddresses', 'phoneNumbers', 'assignments.customer']);

        // Name und E-Mail-Adresse als eine Gruppe, damit ein zusaetzlicher
        // Kundenfilter nicht durch das ODER umgangen wird.
        if (filled($eingabe['suchbegriff'] ?? null)) {
            $begriff = '%'.$eingabe['suchbegriff'].'%';

            $query->where(function (Builder $gruppe) use ($begriff): void {
                $gruppe->where('first_name', 'like', $begriff)
                    ->orWhere('last_name', 'like', $begriff)
                    ->orWhereHas(
                        'emailAddresses',
                        fn (Builder $adressen) => $adressen->where('email', 'like', $begriff),
                    );
            });
        }

        if (filled($eingabe['kunde_id'] ?? null)) {
            $query->whereHas(
                'assignments',
                fn (Builder $zuordnungen) => $zuordnungen->where('customer_id', $eingabe['kunde_id']),
            );
        }

        if ($eingabe['nur_aktive'] ?? false) {
            $query->active();
        }

        $ansprechpartner = $query->orderBy('last_name')->orderBy('first_name')
            ->limit($this->limit($eingabe['anzahl'] ?? null))
            ->get();

        return Response::json([
            'anzahl' => $ansprechpartner->count(),
            'ansprechpartner' => $ansprechpartner->map(fn (Contact $kontakt): array => [
                'id' => $kontakt->id,
                'name' => $kontakt->fullName(),
                'email' => $kontakt->primaryEmailAddress()?->email,
                'telefon' => $kontakt->primaryPhoneNumber()?->number,
                'archiviert' => $kontakt->isArchived(),
                'kunden' => $kontakt->assignments
                    ->map(fn (ContactAssignment $zuordnung): string => $zuordnung->customer->displayName())
                    ->all(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()->description('Freitext über Vorname, Nachname und E-Mail-Adresse.'),
            'kunde_id' => $schema->integer()->description('Nur Ansprechpartner, die diesem Kunden zugeordnet sind.'),
            'nur_aktive' => $schema->boolean()->description('Archivierte Ansprechpartner ausblenden.'),
            'anzahl' => $schema->integer()->description('Höchstzahl der Treffer, Standard 25, Maximum 100.'),
        ];
    }
}
