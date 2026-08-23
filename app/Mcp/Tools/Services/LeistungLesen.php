<?php

namespace App\Mcp\Tools\Services;

use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Models\ServiceComponent;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('leistung-lesen')]
#[Description('Liefert eine Kundenleistung vollständig: Preise, Marge, Abrechnungsintervall, Termine, Katalogherkunft samt Snapshot, Bestandteile, Tags und geplante Preisänderungen.')]
#[IsReadOnly]
class LeistungLesen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $leistung = CustomerService::query()
            ->with([
                'customer', 'product', 'productVariant', 'category', 'subcategory',
                'responsibleUser', 'serviceComponents', 'tags', 'priceChanges',
            ])
            ->find($eingabe['id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        return Response::json([
            'id' => $leistung->id,
            'name' => $leistung->name,
            'abrechnungsbezeichnung' => $leistung->billing_label,
            'beschreibung' => $leistung->description,
            'status' => $leistung->status->value,
            'archiviert' => $leistung->isArchived(),
            'kunde' => [
                'id' => $leistung->customer_id,
                'kundennummer' => $leistung->customer->customer_number,
                'anzeigename' => $leistung->customer->displayName(),
            ],
            'katalogartikel' => $leistung->product?->only(['id', 'name', 'internal_name']),
            'variante' => $leistung->productVariant?->only(['id', 'name']),
            'katalog_snapshot' => $leistung->catalog_snapshot,
            'kategorie' => $leistung->category?->only(['id', 'name']),
            'unterkategorie' => $leistung->subcategory?->only(['id', 'name']),
            'verantwortlicher' => $leistung->responsibleUser?->only(['id', 'name']),
            'einkaufspreis' => $this->money($leistung->purchase_price_cents),
            'verkaufspreis' => $this->money($leistung->sales_price_cents),
            'marge' => $this->money($leistung->margin()->cents),
            'marge_prozent' => $leistung->marginPercentage(),
            'abrechnungsintervall' => [
                'einheit' => $leistung->billing_interval_unit->value,
                'anzahl' => $leistung->billing_interval_count,
                'bezeichnung' => $leistung->billingInterval()->label(),
            ],
            'umsatz_monat' => $this->money($leistung->monthlyRevenue()->cents),
            'umsatz_jahr' => $this->money($leistung->yearlyRevenue()->cents),
            'kosten_monat' => $this->money($leistung->monthlyCosts()->cents),
            'marge_monat' => $this->money($leistung->monthlyMargin()->cents),
            'zaehlt_zum_umsatz' => $leistung->countsTowardsRevenue(),
            'leistungsbeginn' => $this->date($leistung->service_start_date),
            'abrechnungsbeginn' => $this->date($leistung->billing_start_date),
            'erste_abrechnung' => $this->date($leistung->first_billing_date),
            'nicht_abrechnen' => [
                'aktiv' => $leistung->do_not_bill,
                'grund' => $leistung->do_not_bill_reason?->value,
                'seit' => $this->dateTime($leistung->do_not_bill_since),
                'aufgehoben_am' => $this->dateTime($leistung->do_not_bill_released_at),
            ],
            'leistungsbestandteile' => $leistung->serviceComponents
                ->map(fn (ServiceComponent $bestandteil): array => [
                    'id' => $bestandteil->id,
                    'name' => $bestandteil->name,
                    'beschreibung' => $bestandteil->description,
                ])->all(),
            'tags' => $leistung->tags->map(fn (Tag $tag): string => $tag->name)->all(),
            'geplante_preisaenderungen' => $leistung->priceChanges
                ->filter(fn (PriceChange $aenderung): bool => is_null($aenderung->applied_at))
                ->map(fn (PriceChange $aenderung): array => [
                    'id' => $aenderung->id,
                    'preisart' => $aenderung->price_type->value,
                    'neuer_preis' => $this->money($aenderung->new_price_cents),
                    'wirksam_ab' => $this->date($aenderung->effective_date),
                ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID der Kundenleistung.')->required(),
        ];
    }
}
