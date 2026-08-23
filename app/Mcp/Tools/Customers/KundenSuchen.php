<?php

namespace App\Mcp\Tools\Customers;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Mcp\Tools\PortalTool;
use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('kunden-suchen')]
#[Description('Durchsucht die Kunden nach Kundennummer, Firmenname, Personenname, Kurzbezeichnung oder internem Kürzel. Liefert eine Liste mit Kennzahlen je Kunde.')]
#[IsReadOnly]
class KundenSuchen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'suchbegriff' => ['nullable', 'string', 'max:255'],
            'typ' => ['nullable', 'string', 'in:company,private'],
            'status' => ['nullable', 'string', 'in:active,archived'],
            'verantwortlicher_id' => ['nullable', 'integer'],
            'anzahl' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Customer::query()->with('responsibleUser')->withCount('services');

        $this->applySearch($query, $eingabe['suchbegriff'] ?? null, [
            'customer_number', 'company_name', 'first_name', 'last_name', 'short_label', 'internal_code',
        ]);

        if (filled($eingabe['typ'] ?? null)) {
            $query->where('type', CustomerType::from($eingabe['typ']));
        }

        // Ohne Statusfilter werden archivierte Kunden mit geliefert, damit das
        // Werkzeug den vollstaendigen Bestand sichtbar macht.
        if (filled($eingabe['status'] ?? null)) {
            $query->where('status', CustomerStatus::from($eingabe['status']));
        }

        if (filled($eingabe['verantwortlicher_id'] ?? null)) {
            $query->where('responsible_user_id', $eingabe['verantwortlicher_id']);
        }

        $kunden = $query->orderBy('customer_number')
            ->limit($this->limit($eingabe['anzahl'] ?? null))
            ->get();

        return Response::json([
            'anzahl' => $kunden->count(),
            'kunden' => $kunden->map(fn (Customer $kunde): array => [
                'id' => $kunde->id,
                'kundennummer' => $kunde->customer_number,
                'anzeigename' => $kunde->displayName(),
                'typ' => $kunde->type->value,
                'status' => $kunde->status->value,
                'kurzbezeichnung' => $kunde->short_label,
                'internes_kuerzel' => $kunde->internal_code,
                'verantwortlicher' => $kunde->responsibleUser?->name,
                'anzahl_leistungen' => $kunde->services_count,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()
                ->description('Freitext über Kundennummer, Name, Kurzbezeichnung und internes Kürzel.'),
            'typ' => $schema->string()
                ->enum(['company', 'private'])
                ->description('Auf Firmen- oder Privatkunden einschränken.'),
            'status' => $schema->string()
                ->enum(['active', 'archived'])
                ->description('Ohne Angabe werden aktive und archivierte Kunden geliefert.'),
            'verantwortlicher_id' => $schema->integer()
                ->description('Nur Kunden dieses internen Verantwortlichen.'),
            'anzahl' => $schema->integer()
                ->description('Höchstzahl der Treffer, Standard 25, Maximum 100.'),
        ];
    }
}
