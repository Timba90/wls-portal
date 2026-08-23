<?php

namespace App\Mcp\Tools\Pricing;

use App\Actions\Pricing\CancelPriceChange;
use App\Mcp\Tools\PortalTool;
use App\Models\PriceChange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('preisaenderung-abbrechen')]
#[Description('Verwirft eine geplante, noch nicht wirksam gewordene Preisänderung. Bereits wirksame Änderungen bleiben als Verlauf bestehen und lassen sich nicht entfernen.')]
class PreisaenderungAbbrechen extends PortalTool
{
    public function __construct(private readonly CancelPriceChange $cancelPriceChange) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $aenderung = PriceChange::query()->with('customerService')->find($eingabe['id']);

        if (! $aenderung instanceof PriceChange) {
            return Response::error('Preisänderung nicht gefunden.');
        }

        if ($aenderung->isApplied()) {
            return Response::error(
                'Diese Preisänderung ist am '
                .$aenderung->applied_at?->format('d.m.Y')
                .' wirksam geworden und bleibt als Verlauf bestehen.'
            );
        }

        $beschreibung = [
            'leistung_id' => $aenderung->customer_service_id,
            'leistung' => $aenderung->customerService->name,
            'preisart' => $aenderung->price_type->value,
            'neuer_preis' => $this->money($aenderung->new_price_cents),
            'wirksam_ab' => $this->date($aenderung->effective_date),
        ];

        ($this->cancelPriceChange)($aenderung);

        return Response::json(['abgebrochen' => true, ...$beschreibung]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Interne ID der geplanten Preisänderung, zu finden über preisverlauf-lesen.')
                ->required(),
        ];
    }
}
