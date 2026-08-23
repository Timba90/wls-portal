<?php

namespace App\Mcp\Tools\Contacts;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\UpdateContact;
use App\Mcp\Tools\PortalTool;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\EmailAddress;
use App\Models\PhoneNumber;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('ansprechpartner-speichern')]
#[Description('Legt einen Ansprechpartner an oder ändert einen bestehenden. Ein Ansprechpartner braucht mindestens eine Kundenzuordnung; die Zuordnungen werden bei Angabe vollständig ersetzt.')]
class AnsprechpartnerSpeichern extends PortalTool
{
    public function __construct(
        private readonly CreateContact $createContact,
        private readonly UpdateContact $updateContact,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'anrede' => ['nullable', 'string', 'in:herr,frau,neutral'],
            'akademischer_titel' => ['nullable', 'string', 'max:64'],
            'vorname' => ['nullable', 'string', 'max:255'],
            'nachname' => ['nullable', 'string', 'max:255'],
            'geschlecht' => ['nullable', 'string', 'in:male,female,diverse'],
            'geburtsdatum' => ['nullable', 'date'],
            'bevorzugte_kontaktart' => ['nullable', 'string', 'in:email,phone,mobile'],
            'zuordnungen' => ['nullable', 'array'],
            'zuordnungen.*.kunde_id' => ['required', 'integer', 'exists:customers,id'],
            'zuordnungen.*.rollen_ids' => ['nullable', 'array'],
            'zuordnungen.*.rollen_ids.*' => ['integer', 'exists:contact_roles,id'],
            'zuordnungen.*.hauptansprechpartner' => ['nullable', 'boolean'],
            'zuordnungen.*.rechnungskontakt' => ['nullable', 'boolean'],
            'zuordnungen.*.aktiv' => ['nullable', 'boolean'],
            'zuordnungen.*.prioritaet' => ['nullable', 'integer', 'min:1'],
            'zuordnungen.*.notiz' => ['nullable', 'string'],
            'email_adressen' => ['nullable', 'array'],
            'email_adressen.*.email' => ['required', 'email'],
            'email_adressen.*.art' => ['required', 'string', 'in:business,private,mobile'],
            'email_adressen.*.primaer' => ['nullable', 'boolean'],
            'telefonnummern' => ['nullable', 'array'],
            'telefonnummern.*.nummer' => ['required', 'string', 'max:64'],
            'telefonnummern.*.art' => ['required', 'string', 'in:business,private,mobile'],
            'telefonnummern.*.primaer' => ['nullable', 'boolean'],
        ]);

        return filled($eingabe['id'] ?? null)
            ? $this->update($eingabe)
            : $this->create($eingabe);
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function create(array $eingabe): Response
    {
        if (blank($eingabe['vorname'] ?? null) || blank($eingabe['nachname'] ?? null)) {
            return Response::error('Beim Anlegen sind Vor- und Nachname erforderlich.');
        }

        if (blank($eingabe['zuordnungen'] ?? null)) {
            return Response::error('Ein Ansprechpartner muss mindestens einem Kunden zugeordnet sein.');
        }

        $kontakt = ($this->createContact)(
            $this->attributes($eingabe),
            $this->assignments($eingabe['zuordnungen']),
            $this->emails($eingabe['email_adressen'] ?? []),
            $this->phones($eingabe['telefonnummern'] ?? []),
        );

        return $this->respond($kontakt, 'angelegt');
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function update(array $eingabe): Response
    {
        $kontakt = Contact::query()
            ->with(['assignments.roles', 'emailAddresses', 'phoneNumbers'])
            ->find($eingabe['id']);

        if (! $kontakt instanceof Contact) {
            return Response::error('Ansprechpartner nicht gefunden.');
        }

        // Nicht angegebene Felder und Listen behalten ihren Bestand, damit ein
        // gezielter Aufruf nicht ungewollt andere Daten leert.
        $eingabe['vorname'] ??= $kontakt->first_name;
        $eingabe['nachname'] ??= $kontakt->last_name;
        $eingabe['anrede'] ??= $kontakt->salutation?->value;
        $eingabe['akademischer_titel'] ??= $kontakt->academic_title;
        $eingabe['geschlecht'] ??= $kontakt->gender?->value;
        $eingabe['geburtsdatum'] ??= $this->date($kontakt->birth_date);
        $eingabe['bevorzugte_kontaktart'] ??= $kontakt->preferred_contact_method?->value;

        $zuordnungen = is_null($eingabe['zuordnungen'] ?? null)
            ? $kontakt->assignments->map(fn (ContactAssignment $zuordnung): array => [
                'customer_id' => $zuordnung->customer_id,
                'role_ids' => $zuordnung->roles->pluck('id')->all(),
                'is_primary_contact' => $zuordnung->is_primary_contact,
                'is_billing_contact' => $zuordnung->is_billing_contact,
                'is_active' => $zuordnung->is_active,
                'priority' => $zuordnung->priority,
                'note' => $zuordnung->note,
            ])->all()
            : $this->assignments($eingabe['zuordnungen']);

        $emails = is_null($eingabe['email_adressen'] ?? null)
            ? $kontakt->emailAddresses->map(fn (EmailAddress $adresse): array => [
                'email' => $adresse->email,
                'type' => $adresse->type->value,
                'is_primary' => $adresse->is_primary,
            ])->all()
            : $this->emails($eingabe['email_adressen']);

        $phones = is_null($eingabe['telefonnummern'] ?? null)
            ? $kontakt->phoneNumbers->map(fn (PhoneNumber $nummer): array => [
                'number' => $nummer->number,
                'type' => $nummer->type->value,
                'is_primary' => $nummer->is_primary,
            ])->all()
            : $this->phones($eingabe['telefonnummern']);

        $kontakt = ($this->updateContact)($kontakt, $this->attributes($eingabe), $zuordnungen, $emails, $phones);

        return $this->respond($kontakt, 'geändert');
    }

    /**
     * @param  array<string, mixed>  $eingabe
     * @return array<string, mixed>
     */
    private function attributes(array $eingabe): array
    {
        return [
            'salutation' => $eingabe['anrede'] ?? null,
            'academic_title' => $eingabe['akademischer_titel'] ?? null,
            'first_name' => $eingabe['vorname'] ?? null,
            'last_name' => $eingabe['nachname'] ?? null,
            'gender' => $eingabe['geschlecht'] ?? null,
            'birth_date' => $eingabe['geburtsdatum'] ?? null,
            'preferred_contact_method' => $eingabe['bevorzugte_kontaktart'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $zuordnungen
     * @return array<int, array<string, mixed>>
     */
    private function assignments(array $zuordnungen): array
    {
        return collect($zuordnungen)->map(fn (array $zuordnung, int $index): array => [
            'customer_id' => (int) $zuordnung['kunde_id'],
            'role_ids' => $zuordnung['rollen_ids'] ?? [],
            'is_primary_contact' => (bool) ($zuordnung['hauptansprechpartner'] ?? false),
            'is_billing_contact' => (bool) ($zuordnung['rechnungskontakt'] ?? false),
            'is_active' => (bool) ($zuordnung['aktiv'] ?? true),
            'priority' => (int) ($zuordnung['prioritaet'] ?? $index + 1),
            'note' => $zuordnung['notiz'] ?? null,
        ])->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $adressen
     * @return array<int, array<string, mixed>>
     */
    private function emails(array $adressen): array
    {
        return collect($adressen)->map(fn (array $adresse): array => [
            'email' => $adresse['email'],
            'type' => $adresse['art'],
            'is_primary' => (bool) ($adresse['primaer'] ?? false),
        ])->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $nummern
     * @return array<int, array<string, mixed>>
     */
    private function phones(array $nummern): array
    {
        return collect($nummern)->map(fn (array $nummer): array => [
            'number' => $nummer['nummer'],
            'type' => $nummer['art'],
            'is_primary' => (bool) ($nummer['primaer'] ?? false),
        ])->all();
    }

    private function respond(Contact $kontakt, string $vorgang): Response
    {
        return Response::json([
            'vorgang' => $vorgang,
            'id' => $kontakt->id,
            'name' => $kontakt->fullName(),
            'anzahl_zuordnungen' => $kontakt->assignments()->count(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Zum Ändern angeben, zum Anlegen weglassen.'),
            'anrede' => $schema->string()->enum(['herr', 'frau', 'neutral']),
            'akademischer_titel' => $schema->string(),
            'vorname' => $schema->string()->description('Pflicht beim Anlegen.'),
            'nachname' => $schema->string()->description('Pflicht beim Anlegen.'),
            'geschlecht' => $schema->string()->enum(['male', 'female', 'diverse']),
            'geburtsdatum' => $schema->string()->description('Datum in der Form JJJJ-MM-TT.'),
            'bevorzugte_kontaktart' => $schema->string()->enum(['email', 'phone', 'mobile']),
            'zuordnungen' => $schema->array()
                ->description('Kundenzuordnungen. Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten. Je Eintrag: kunde_id, rollen_ids, hauptansprechpartner, rechnungskontakt, aktiv, prioritaet, notiz.'),
            'email_adressen' => $schema->array()
                ->description('Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten. Je Eintrag: email, art (business|private|mobile), primaer.'),
            'telefonnummern' => $schema->array()
                ->description('Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten. Je Eintrag: nummer, art (business|private|mobile), primaer.'),
        ];
    }
}
