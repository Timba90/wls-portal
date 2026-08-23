<?php

namespace App\Mcp\Tools\Services;

use App\Enums\CustomerServiceStatus;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('leistungen-suchen')]
#[Description('Durchsucht die Kundenleistungen über alle Kunden hinweg. Lässt sich auf Kunde, Status, Katalogartikel und die Kennzeichnung „bewusst nicht abrechnen" einschränken und liefert Preise, Marge und Intervall.')]
#[IsReadOnly]
class LeistungenSuchen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'suchbegriff' => ['nullable', 'string', 'max:255'],
            'kunde_id' => ['nullable', 'integer'],
            'produkt_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:planned,active,paused,ended,archived'],
            'nicht_abrechnen' => ['nullable', 'boolean'],
            'anzahl' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = CustomerService::query()->with(['customer', 'product']);

        $this->applySearch($query, $eingabe['suchbegriff'] ?? null, ['name', 'billing_label', 'description']);

        foreach (['kunde_id' => 'customer_id', 'produkt_id' => 'product_id'] as $feld => $spalte) {
            if (filled($eingabe[$feld] ?? null)) {
                $query->where($spalte, $eingabe[$feld]);
            }
        }

        if (filled($eingabe['status'] ?? null)) {
            $query->where('status', CustomerServiceStatus::from($eingabe['status']));
        }

        if (! is_null($eingabe['nicht_abrechnen'] ?? null)) {
            $query->where('do_not_bill', $eingabe['nicht_abrechnen']);
        }

        $leistungen = $query->orderBy('name')
            ->limit($this->limit($eingabe['anzahl'] ?? null))
            ->get();

        return Response::json([
            'anzahl' => $leistungen->count(),
            'summe_umsatz_monat' => $this->money(
                $leistungen->filter(fn (CustomerService $leistung): bool => $leistung->countsTowardsRevenue())
                    ->sum(fn (CustomerService $leistung): int => $leistung->monthlyRevenue()->cents),
            ),
            'leistungen' => $leistungen->map(fn (CustomerService $leistung): array => [
                'id' => $leistung->id,
                'name' => $leistung->name,
                'kunde_id' => $leistung->customer_id,
                'kunde' => $leistung->customer->displayName(),
                'kundennummer' => $leistung->customer->customer_number,
                'katalogartikel' => $leistung->product?->name,
                'status' => $leistung->status->value,
                'einkaufspreis' => $this->money($leistung->purchase_price_cents),
                'verkaufspreis' => $this->money($leistung->sales_price_cents),
                'marge' => $this->money($leistung->margin()->cents),
                'marge_prozent' => $leistung->marginPercentage(),
                'abrechnungsintervall' => $leistung->billingInterval()->label(),
                'umsatz_monat' => $this->money($leistung->monthlyRevenue()->cents),
                'nicht_abrechnen' => $leistung->do_not_bill,
                'nicht_abrechnen_grund' => $leistung->do_not_bill_reason?->value,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()->description('Freitext über Name, Abrechnungsbezeichnung und Beschreibung.'),
            'kunde_id' => $schema->integer()->description('Nur Leistungen dieses Kunden.'),
            'produkt_id' => $schema->integer()->description('Nur Leistungen, die auf diesem Katalogartikel beruhen.'),
            'status' => $schema->string()->enum(['planned', 'active', 'paused', 'ended', 'archived']),
            'nicht_abrechnen' => $schema->boolean()
                ->description('true liefert nur bewusst nicht abgerechnete Leistungen, false nur die übrigen.'),
            'anzahl' => $schema->integer()->description('Höchstzahl der Treffer, Standard 25, Maximum 100.'),
        ];
    }
}
