<?php

namespace App\Mcp\Tools\Insights;

use App\Actions\Reporting\CalculatePortalMetrics;
use App\Mcp\Tools\PortalTool;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('kennzahlen-lesen')]
#[Description('Liefert den Gesamtüberblick: Bestandszahlen sowie Soll-Umsatz, Kosten und Marge je Monat und Jahr. Einmalige Leistungen sowie pausierte und bewusst nicht abgerechnete Leistungen zählen nicht zum Soll-Umsatz und werden getrennt ausgewiesen.')]
#[IsReadOnly]
class KennzahlenLesen extends PortalTool
{
    public function __construct(private readonly CalculatePortalMetrics $calculatePortalMetrics) {}

    public function handle(Request $request): Response
    {
        $kennzahlen = ($this->calculatePortalMetrics)();

        return Response::json([
            'bestand' => [
                'kunden_aktiv' => $kennzahlen['activeCustomers'],
                'kunden_archiviert' => $kennzahlen['archivedCustomers'],
                'ansprechpartner_aktiv' => $kennzahlen['activeContacts'],
                'katalogartikel_aktiv' => $kennzahlen['products'],
                'leistungen_aktiv' => $kennzahlen['activeServices'],
            ],
            'umsatzrelevanz' => [
                'abrechnungsrelevante_leistungen' => $kennzahlen['billableServices'],
                'bewusst_nicht_abgerechnet' => $kennzahlen['doNotBillServices'],
                'einmalige_leistungen' => $kennzahlen['oneTimeServices'],
            ],
            'monat' => [
                'umsatz' => $this->fromMoney($kennzahlen['monthlyRevenue']),
                'kosten' => $this->fromMoney($kennzahlen['monthlyCosts']),
                'marge' => $this->fromMoney($kennzahlen['monthlyMargin']),
            ],
            'jahr' => [
                'umsatz' => $this->fromMoney($kennzahlen['yearlyRevenue']),
                'kosten' => $this->fromMoney($kennzahlen['yearlyCosts']),
                'marge' => $this->fromMoney($kennzahlen['yearlyMargin']),
            ],
            'marge_prozent' => $kennzahlen['marginPercentage'],
        ]);
    }

    /**
     * @return array{cents: int, formatiert: string}
     */
    private function fromMoney(Money $betrag): array
    {
        return $this->money($betrag->cents);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
