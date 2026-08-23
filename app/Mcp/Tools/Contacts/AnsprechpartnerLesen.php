<?php

namespace App\Mcp\Tools\Contacts;

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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ansprechpartner-lesen')]
#[Description('Liefert einen Ansprechpartner vollständig: Stammdaten, Kontaktkanäle und alle Kundenzuordnungen mit Rollen, Priorität und wirksamen Kontaktdaten.')]
#[IsReadOnly]
class AnsprechpartnerLesen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $kontakt = Contact::query()
            ->with(['emailAddresses', 'phoneNumbers', 'assignments.customer', 'assignments.roles'])
            ->find($eingabe['id']);

        if (! $kontakt instanceof Contact) {
            return Response::error('Ansprechpartner nicht gefunden.');
        }

        return Response::json([
            'id' => $kontakt->id,
            'name' => $kontakt->fullName(),
            'anrede' => $kontakt->salutation?->value,
            'akademischer_titel' => $kontakt->academic_title,
            'vorname' => $kontakt->first_name,
            'nachname' => $kontakt->last_name,
            'geschlecht' => $kontakt->gender?->value,
            'geburtsdatum' => $this->date($kontakt->birth_date),
            'bevorzugte_kontaktart' => $kontakt->preferred_contact_method?->value,
            'archiviert' => $kontakt->isArchived(),
            'archiviert_am' => $this->dateTime($kontakt->archived_at),
            'email_adressen' => $kontakt->emailAddresses->map(fn (EmailAddress $adresse): array => [
                'id' => $adresse->id,
                'email' => $adresse->email,
                'art' => $adresse->type->value,
                'primaer' => $adresse->is_primary,
            ])->all(),
            'telefonnummern' => $kontakt->phoneNumbers->map(fn (PhoneNumber $nummer): array => [
                'id' => $nummer->id,
                'nummer' => $nummer->number,
                'art' => $nummer->type->value,
                'primaer' => $nummer->is_primary,
            ])->all(),
            'zuordnungen' => $kontakt->assignments->map(fn (ContactAssignment $zuordnung): array => [
                'zuordnung_id' => $zuordnung->id,
                'kunde_id' => $zuordnung->customer_id,
                'kundennummer' => $zuordnung->customer->customer_number,
                'kunde' => $zuordnung->customer->displayName(),
                'rollen' => $zuordnung->roles->pluck('name')->all(),
                'rollen_ids' => $zuordnung->roles->pluck('id')->all(),
                'hauptansprechpartner' => $zuordnung->is_primary_contact,
                'rechnungskontakt' => $zuordnung->is_billing_contact,
                'aktiv' => $zuordnung->is_active,
                'prioritaet' => $zuordnung->priority,
                'wirksame_email' => $zuordnung->effectiveEmail()?->email,
                'wirksames_telefon' => $zuordnung->effectivePhone()?->number,
                'notiz' => $zuordnung->note,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Ansprechpartners.')->required(),
        ];
    }
}
