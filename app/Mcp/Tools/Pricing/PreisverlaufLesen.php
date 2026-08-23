<?php

namespace App\Mcp\Tools\Pricing;

use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use App\Models\PriceChange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('preisverlauf-lesen')]
#[Description('Liefert den vollständigen Preisverlauf einer Kundenleistung: bereits wirksam gewordene Änderungen und die noch geplanten, jeweils mit altem und neuem Preis.')]
#[IsReadOnly]
class PreisverlaufLesen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'leistung_id' => ['required', 'integer'],
        ]);

        $leistung = CustomerService::query()
            ->with(['priceChanges.user'])
            ->find($eingabe['leistung_id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        $verlauf = $leistung->priceChanges
            ->sortBy(['effective_date', 'id'])
            ->map(fn (PriceChange $aenderung): array => [
                'id' => $aenderung->id,
                'preisart' => $aenderung->price_type->value,
                'alter_preis' => is_null($aenderung->old_price_cents)
                    ? null
                    : $this->money($aenderung->old_price_cents),
                'neuer_preis' => $this->money($aenderung->new_price_cents),
                'wirksam_ab' => $this->date($aenderung->effective_date),
                'wirksam_geworden_am' => $this->dateTime($aenderung->applied_at),
                'geplant' => $aenderung->isScheduled(),
                'erfasst_von' => $aenderung->user?->name,
                'notiz' => $aenderung->note,
            ])->values();

        return Response::json([
            'leistung_id' => $leistung->id,
            'name' => $leistung->name,
            'aktueller_einkaufspreis' => $this->money($leistung->purchase_price_cents),
            'aktueller_verkaufspreis' => $this->money($leistung->sales_price_cents),
            'anzahl_eintraege' => $verlauf->count(),
            'verlauf' => $verlauf->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leistung_id' => $schema->integer()->description('Interne ID der Kundenleistung.')->required(),
        ];
    }
}
