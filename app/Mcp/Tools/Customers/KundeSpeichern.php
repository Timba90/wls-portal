<?php

namespace App\Mcp\Tools\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Mcp\Tools\PortalTool;
use App\Models\Customer;
use App\Models\EmailAddress;
use App\Models\PhoneNumber;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('kunde-speichern')]
#[Description('Legt einen Kunden an oder ändert einen bestehenden. Ohne ID entsteht ein neuer Kunde samt Kundennummer; mit ID werden die Stammdaten überschrieben. Der Kundentyp lässt sich nachträglich nicht wechseln.')]
class KundeSpeichern extends PortalTool
{
    public function __construct(
        private readonly CreateCustomer $createCustomer,
        private readonly UpdateCustomer $updateCustomer,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'typ' => ['nullable', 'string', 'in:company,private'],
            'firmenname' => ['nullable', 'string', 'max:255'],
            'anrede' => ['nullable', 'string', 'in:herr,frau,neutral'],
            'akademischer_titel' => ['nullable', 'string', 'max:64'],
            'vorname' => ['nullable', 'string', 'max:255'],
            'nachname' => ['nullable', 'string', 'max:255'],
            'geburtsdatum' => ['nullable', 'date'],
            'geschlecht' => ['nullable', 'string', 'in:male,female,diverse'],
            'kurzbezeichnung' => ['nullable', 'string', 'max:255'],
            'internes_kuerzel' => ['nullable', 'string', 'max:64'],
            'verantwortlicher_id' => ['nullable', 'integer', 'exists:users,id'],
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
        foreach (['typ', 'kurzbezeichnung', 'internes_kuerzel'] as $pflichtfeld) {
            if (blank($eingabe[$pflichtfeld] ?? null)) {
                return Response::error("Beim Anlegen ist „{$pflichtfeld}\" erforderlich.");
            }
        }

        if ($eingabe['typ'] === 'company' && blank($eingabe['firmenname'] ?? null)) {
            return Response::error('Firmenkunden brauchen einen Firmennamen.');
        }

        if ($eingabe['typ'] === 'private' && (blank($eingabe['vorname'] ?? null) || blank($eingabe['nachname'] ?? null))) {
            return Response::error('Privatkunden brauchen Vor- und Nachnamen.');
        }

        $kunde = ($this->createCustomer)($this->attributes($eingabe));

        return $this->respond($kunde, 'angelegt');
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function update(array $eingabe): Response
    {
        $kunde = Customer::query()->find($eingabe['id']);

        if (! $kunde instanceof Customer) {
            return Response::error('Kunde nicht gefunden.');
        }

        // Fehlende Felder werden aus dem Bestand ergaenzt, damit ein Aufruf mit
        // nur einem geaenderten Feld nicht den Rest leert.
        $eingabe['kurzbezeichnung'] ??= $kunde->short_label;
        $eingabe['internes_kuerzel'] ??= $kunde->internal_code;
        $eingabe['firmenname'] ??= $kunde->company_name;
        $eingabe['vorname'] ??= $kunde->first_name;
        $eingabe['nachname'] ??= $kunde->last_name;
        $eingabe['anrede'] ??= $kunde->salutation?->value;
        $eingabe['akademischer_titel'] ??= $kunde->academic_title;
        $eingabe['geburtsdatum'] ??= $this->date($kunde->birth_date);
        $eingabe['geschlecht'] ??= $kunde->gender?->value;
        $eingabe['verantwortlicher_id'] ??= $kunde->responsible_user_id;

        if (! array_key_exists('email_adressen', $eingabe) || is_null($eingabe['email_adressen'])) {
            $eingabe['email_adressen'] = $kunde->emailAddresses
                ->map(fn (EmailAddress $adresse): array => [
                    'email' => $adresse->email,
                    'art' => $adresse->type->value,
                    'primaer' => $adresse->is_primary,
                ])->all();
        }

        if (! array_key_exists('telefonnummern', $eingabe) || is_null($eingabe['telefonnummern'])) {
            $eingabe['telefonnummern'] = $kunde->phoneNumbers
                ->map(fn (PhoneNumber $nummer): array => [
                    'nummer' => $nummer->number,
                    'art' => $nummer->type->value,
                    'primaer' => $nummer->is_primary,
                ])->all();
        }

        $kunde = ($this->updateCustomer)($kunde, $this->attributes($eingabe));

        return $this->respond($kunde, 'geändert');
    }

    /**
     * Uebersetzt die deutschen Feldnamen des Werkzeugs in die Struktur der Actions.
     *
     * @param  array<string, mixed>  $eingabe
     * @return array<string, mixed>
     */
    private function attributes(array $eingabe): array
    {
        return [
            'type' => $eingabe['typ'] ?? null,
            'company_name' => $eingabe['firmenname'] ?? null,
            'salutation' => $eingabe['anrede'] ?? null,
            'academic_title' => $eingabe['akademischer_titel'] ?? null,
            'first_name' => $eingabe['vorname'] ?? null,
            'last_name' => $eingabe['nachname'] ?? null,
            'birth_date' => $eingabe['geburtsdatum'] ?? null,
            'gender' => $eingabe['geschlecht'] ?? null,
            'short_label' => $eingabe['kurzbezeichnung'] ?? '',
            'internal_code' => $eingabe['internes_kuerzel'] ?? '',
            'responsible_user_id' => $eingabe['verantwortlicher_id'] ?? null,
            'emails' => collect($eingabe['email_adressen'] ?? [])
                ->map(fn (array $adresse): array => [
                    'email' => $adresse['email'],
                    'type' => $adresse['art'],
                    'is_primary' => (bool) ($adresse['primaer'] ?? false),
                ])->all(),
            'phones' => collect($eingabe['telefonnummern'] ?? [])
                ->map(fn (array $nummer): array => [
                    'number' => $nummer['nummer'],
                    'type' => $nummer['art'],
                    'is_primary' => (bool) ($nummer['primaer'] ?? false),
                ])->all(),
        ];
    }

    private function respond(Customer $kunde, string $vorgang): Response
    {
        return Response::json([
            'vorgang' => $vorgang,
            'id' => $kunde->id,
            'kundennummer' => $kunde->customer_number,
            'anzeigename' => $kunde->displayName(),
            'typ' => $kunde->type->value,
            'status' => $kunde->status->value,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Zum Ändern angeben, zum Anlegen weglassen.'),
            'typ' => $schema->string()->enum(['company', 'private'])
                ->description('Nur beim Anlegen wirksam; ein bestehender Kunde behält seinen Typ.'),
            'firmenname' => $schema->string()->description('Pflicht bei Firmenkunden.'),
            'anrede' => $schema->string()->enum(['herr', 'frau', 'neutral']),
            'akademischer_titel' => $schema->string(),
            'vorname' => $schema->string()->description('Pflicht bei Privatkunden.'),
            'nachname' => $schema->string()->description('Pflicht bei Privatkunden.'),
            'geburtsdatum' => $schema->string()->description('Datum in der Form JJJJ-MM-TT.'),
            'geschlecht' => $schema->string()->enum(['male', 'female', 'diverse']),
            'kurzbezeichnung' => $schema->string()->description('Pflicht beim Anlegen. Muss nicht eindeutig sein.'),
            'internes_kuerzel' => $schema->string()->description('Pflicht beim Anlegen. Muss nicht eindeutig sein.'),
            'verantwortlicher_id' => $schema->integer()->description('Benutzer-ID des internen Verantwortlichen.'),
            'email_adressen' => $schema->array()
                ->description('Nur bei Privatkunden. Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten. Je Eintrag: email, art (business|private|mobile), primaer.'),
            'telefonnummern' => $schema->array()
                ->description('Nur bei Privatkunden. Ersetzt den Bestand vollständig; ohne Angabe bleibt er erhalten. Je Eintrag: nummer, art (business|private|mobile), primaer.'),
        ];
    }
}
