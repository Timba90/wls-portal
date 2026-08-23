<?php

namespace App\Mcp\Tools\Customers;

use App\Mcp\Tools\PortalTool;
use App\Models\ContactAssignment;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\EmailAddress;
use App\Models\PhoneNumber;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('kunde-lesen')]
#[Description('Liefert einen Kunden vollständig: Stammdaten, Kontaktkanäle, Ansprechpartner, Leistungen und Umsatzkennzahlen. Kann über die ID oder die Kundennummer angesprochen werden.')]
#[IsReadOnly]
class KundeLesen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['nullable', 'integer'],
            'kundennummer' => ['nullable', 'string', 'max:32'],
        ]);

        $kunde = $this->resolveCustomer($eingabe);

        if (! $kunde instanceof Customer) {
            return Response::error('Kunde nicht gefunden.');
        }

        $kunde->load([
            'responsibleUser',
            'emailAddresses',
            'phoneNumbers',
            'services',
            'contactAssignments.contact',
            'contactAssignments.roles',
        ]);

        return Response::json([
            'id' => $kunde->id,
            'kundennummer' => $kunde->customer_number,
            'anzeigename' => $kunde->displayName(),
            'typ' => $kunde->type->value,
            'status' => $kunde->status->value,
            'firmenname' => $kunde->company_name,
            'anrede' => $kunde->salutation?->value,
            'akademischer_titel' => $kunde->academic_title,
            'vorname' => $kunde->first_name,
            'nachname' => $kunde->last_name,
            'geburtsdatum' => $this->date($kunde->birth_date),
            'geschlecht' => $kunde->gender?->value,
            'kurzbezeichnung' => $kunde->short_label,
            'internes_kuerzel' => $kunde->internal_code,
            'verantwortlicher' => $kunde->responsibleUser?->only(['id', 'name', 'email']),
            'archiviert_am' => $this->dateTime($kunde->archived_at),
            'email_adressen' => $kunde->emailAddresses->map(fn (EmailAddress $adresse): array => [
                'id' => $adresse->id,
                'email' => $adresse->email,
                'art' => $adresse->type->value,
                'primaer' => $adresse->is_primary,
            ])->all(),
            'telefonnummern' => $kunde->phoneNumbers->map(fn (PhoneNumber $nummer): array => [
                'id' => $nummer->id,
                'nummer' => $nummer->number,
                'art' => $nummer->type->value,
                'primaer' => $nummer->is_primary,
            ])->all(),
            'ansprechpartner' => $kunde->contactAssignments->map(fn (ContactAssignment $zuordnung): array => [
                'zuordnung_id' => $zuordnung->id,
                'ansprechpartner_id' => $zuordnung->contact_id,
                'name' => $zuordnung->contact->fullName(),
                'rollen' => $zuordnung->roles->pluck('name')->all(),
                'hauptansprechpartner' => $zuordnung->is_primary_contact,
                'rechnungskontakt' => $zuordnung->is_billing_contact,
                'aktiv' => $zuordnung->is_active,
                'prioritaet' => $zuordnung->priority,
            ])->all(),
            'leistungen' => $kunde->services->map(fn (CustomerService $leistung): array => [
                'id' => $leistung->id,
                'name' => $leistung->name,
                'status' => $leistung->status->value,
                'verkaufspreis' => $this->money($leistung->sales_price_cents),
                'abrechnungsintervall' => $leistung->billingInterval()->label(),
            ])->all(),
            'kennzahlen' => [
                'umsatz_monat' => $this->money($kunde->monthlyRevenue()->cents),
                'umsatz_jahr' => $this->money($kunde->yearlyRevenue()->cents),
                'kosten_monat' => $this->money($kunde->monthlyCosts()->cents),
                'marge_monat' => $this->money($kunde->monthlyMargin()->cents),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function resolveCustomer(array $eingabe): ?Customer
    {
        if (filled($eingabe['id'] ?? null)) {
            return Customer::query()->find($eingabe['id']);
        }

        if (filled($eingabe['kundennummer'] ?? null)) {
            return Customer::query()->where('customer_number', $eingabe['kundennummer'])->first();
        }

        return null;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Kunden.'),
            'kundennummer' => $schema->string()->description('Kundennummer in der Form KD-00001. Alternative zur ID.'),
        ];
    }
}
