<?php

namespace App\Mcp\Tools\Pricing;

use App\Actions\Pricing\SchedulePriceChange;
use App\Enums\PriceType;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('preisaenderung-planen')]
#[Description('Plant eine Preisänderung zu einem Datum. Der alte Preis bleibt im Preisverlauf erhalten. Zum heutigen Tag geplante Änderungen greifen sofort; rückwirkende Änderungen sind ausgeschlossen. Mehrere zukünftige Änderungen dürfen nebeneinander bestehen.')]
class PreisaenderungPlanen extends PortalTool
{
    public function __construct(private readonly SchedulePriceChange $schedulePriceChange) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'leistung_id' => ['required', 'integer'],
            'preisart' => ['required', 'string', 'in:sales,purchase'],
            'neuer_preis_cents' => ['required', 'integer', 'min:0'],
            'wirksam_ab' => ['required', 'date'],
            'notiz' => ['nullable', 'string', 'max:500'],
        ]);

        $leistung = CustomerService::query()->find($eingabe['leistung_id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        $aenderung = ($this->schedulePriceChange)(
            $leistung,
            PriceType::from($eingabe['preisart']),
            Money::fromCents($eingabe['neuer_preis_cents']),
            Carbon::parse($eingabe['wirksam_ab']),
            $request->user(),
            $eingabe['notiz'] ?? null,
        );

        return Response::json([
            'id' => $aenderung->id,
            'leistung_id' => $leistung->id,
            'preisart' => $aenderung->price_type->value,
            'alter_preis' => is_null($aenderung->old_price_cents)
                ? null
                : $this->money($aenderung->old_price_cents),
            'neuer_preis' => $this->money($aenderung->new_price_cents),
            'wirksam_ab' => $this->date($aenderung->effective_date),
            'bereits_wirksam' => $aenderung->isApplied(),
            'aktueller_verkaufspreis' => $this->money($leistung->refresh()->sales_price_cents),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leistung_id' => $schema->integer()->description('Interne ID der Kundenleistung.')->required(),
            'preisart' => $schema->string()->enum(['sales', 'purchase'])
                ->description('sales ist der Verkaufspreis, purchase der Einkaufspreis.')
                ->required(),
            'neuer_preis_cents' => $schema->integer()->description('Neuer Preis in ganzen Cent.')->required(),
            'wirksam_ab' => $schema->string()
                ->description('Datum in der Form JJJJ-MM-TT. Heute oder später.')
                ->required(),
            'notiz' => $schema->string()->description('Begründung, die im Preisverlauf erscheint.'),
        ];
    }
}
