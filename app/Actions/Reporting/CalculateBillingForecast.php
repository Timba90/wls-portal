<?php

namespace App\Actions\Reporting;

use App\Enums\BillingIntervalUnit;
use App\Models\CustomerService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Was in den kommenden zwoelf Monaten tatsaechlich abgerechnet wird.
 *
 * Die Kennzahlkacheln zeigen den auf einen Monat normalisierten Umsatz — eine
 * Jahresleistung steht dort mit einem Zwoelftel. Diese Auswertung zeigt
 * stattdessen den Rhythmus: die Jahresleistung faellt in genau einem Monat an,
 * und zwar in dem, in dem sie faellig wird.
 *
 * Bewusst nur nach vorn. Rueckwaerts waere die Reihe falsch, weil seither
 * archivierte Leistungen im heutigen Bestand fehlen und die vergangenen Monate
 * dadurch zu niedrig aussaehen.
 *
 * @phpstan-type ForecastMonth array{key: string, label: string, short: string, amount: Money, count: int}
 * @phpstan-type ForecastShare array{label: string, amount: Money, share: float}
 * @phpstan-type BillingForecast array{
 *     months: array<int, ForecastMonth>,
 *     peak: Money,
 *     total: Money,
 *     average: Money,
 *     composition: array<int, ForecastShare>,
 *     unscheduled: int,
 * }
 */
class CalculateBillingForecast
{
    /**
     * Laenge des Fensters in Monaten.
     */
    private const MONATE = 12;

    /**
     * @return BillingForecast
     */
    public function __invoke(?CarbonImmutable $von = null): array
    {
        $start = ($von ?? CarbonImmutable::now())->startOfMonth();

        $monate = $this->emptyMonths($start);
        $nachKategorie = [];
        $ohnePlan = 0;

        foreach ($this->billableServices() as $leistung) {
            $faellig = $this->dueMonths($leistung, $start);

            if ($faellig === null) {
                $ohnePlan++;

                continue;
            }

            foreach ($faellig as $schluessel => $betrag) {
                $monate[$schluessel]['amount'] = $monate[$schluessel]['amount']->plus($betrag);
                $monate[$schluessel]['count']++;

                $kategorie = $leistung->category?->name ?? 'Ohne Kategorie';
                $nachKategorie[$kategorie] = ($nachKategorie[$kategorie] ?? Money::zero())->plus($betrag);
            }
        }

        $monate = array_values($monate);

        $gesamt = array_reduce(
            $monate,
            fn (Money $summe, array $monat): Money => $summe->plus($monat['amount']),
            Money::zero(),
        );

        return [
            'months' => $monate,
            'peak' => $this->peak($monate),
            'total' => $gesamt,
            'average' => $gesamt->multipliedBy(1 / self::MONATE),
            'composition' => $this->composition($nachKategorie, $gesamt),
            'unscheduled' => $ohnePlan,
        ];
    }

    /**
     * @return Collection<int, CustomerService>
     */
    private function billableServices(): Collection
    {
        return CustomerService::query()
            ->billable()
            ->with('category:id,name')
            ->get();
    }

    /**
     * Die zwoelf Monate des Fensters, jeder zunaechst leer.
     *
     * @return array<string, ForecastMonth>
     */
    private function emptyMonths(CarbonImmutable $start): array
    {
        $monate = [];

        for ($i = 0; $i < self::MONATE; $i++) {
            $monat = $start->addMonths($i);

            $monate[$monat->format('Y-m')] = [
                'key' => $monat->format('Y-m'),
                'label' => $monat->translatedFormat('F Y'),
                'short' => $monat->translatedFormat('M'),
                'amount' => Money::zero(),
                'count' => 0,
            ];
        }

        return $monate;
    }

    /**
     * Die Monate des Fensters, in denen diese Leistung faellig wird, samt
     * Betrag je Faelligkeit.
     *
     * `null` heisst: der Rhythmus laesst sich nicht auf Monate legen, weil das
     * Anfangsdatum fehlt — die Leistung wird getrennt ausgewiesen statt
     * geraten.
     *
     * @return array<string, Money>|null
     */
    private function dueMonths(CustomerService $leistung, CarbonImmutable $start): ?array
    {
        $schrittweite = $this->stepInMonths($leistung);

        // Taeglich oder woechentlich abgerechnete Leistungen fallen in jedem
        // Monat mehrfach an; der Monatswert ist dann der richtige Betrag.
        if ($schrittweite !== null && $schrittweite < 1) {
            return $this->everyMonth($start, $leistung->monthlyRevenue());
        }

        if ($schrittweite === null) {
            return null;
        }

        // Ab hier in ganzen Monaten rechnen; monthsPerUnit() liefert float.
        $schritt = (int) round($schrittweite);
        $anker = $this->anchor($leistung);

        if ($anker === null) {
            // Ohne Anker ist nur der Monatsrhythmus eindeutig: er trifft
            // ohnehin jeden Monat.
            return $schritt === 1
                ? $this->everyMonth($start, $leistung->salesPrice())
                : null;
        }

        return $this->schedule($anker, $schritt, $start, $leistung->salesPrice());
    }

    /**
     * Abstand zweier Faelligkeiten in Monaten; `null` bei einmaligen
     * Leistungen, die hier ohnehin nicht vorkommen.
     */
    private function stepInMonths(CustomerService $leistung): ?float
    {
        $einheit = $leistung->billing_interval_unit;

        if (! $einheit instanceof BillingIntervalUnit || ! $einheit->isRecurring()) {
            return null;
        }

        return $einheit->monthsPerUnit() * max(1, (int) $leistung->billing_interval_count);
    }

    private function anchor(CustomerService $leistung): ?CarbonImmutable
    {
        $datum = $leistung->first_billing_date
            ?? $leistung->billing_start_date
            ?? $leistung->service_start_date;

        return $datum === null ? null : CarbonImmutable::parse($datum)->startOfMonth();
    }

    /**
     * @return array<string, Money>
     */
    private function everyMonth(CarbonImmutable $start, Money $betrag): array
    {
        $monate = [];

        for ($i = 0; $i < self::MONATE; $i++) {
            $monate[$start->addMonths($i)->format('Y-m')] = $betrag;
        }

        return $monate;
    }

    /**
     * Faelligkeiten ab dem Anker im festen Abstand, beschnitten auf das
     * Fenster.
     *
     * @return array<string, Money>
     */
    private function schedule(CarbonImmutable $anker, int $schritt, CarbonImmutable $start, Money $betrag): array
    {
        $ende = $start->addMonths(self::MONATE - 1);

        // Vom Anker aus in ganzen Schritten bis in das Fenster springen, statt
        // Monat fuer Monat zu laufen. `absolute: false` steht hier
        // ausdruecklich: liegt der Anker erst in der Zukunft, ist der Abstand
        // negativ und es wird gar nicht gesprungen — die erste Faelligkeit ist
        // dann der Anker selbst.
        $abstand = $anker->diffInMonths($start, absolute: false);
        $spruenge = $abstand > 0 ? (int) ceil($abstand / $schritt) : 0;

        $faellig = $anker->addMonths($spruenge * $schritt);
        $monate = [];

        while ($faellig->lessThanOrEqualTo($ende)) {
            if ($faellig->greaterThanOrEqualTo($start)) {
                $monate[$faellig->format('Y-m')] = $betrag;
            }

            $faellig = $faellig->addMonths($schritt);
        }

        return $monate;
    }

    /**
     * @param  array<int, ForecastMonth>  $monate
     */
    private function peak(array $monate): Money
    {
        return array_reduce(
            $monate,
            fn (Money $hoechster, array $monat): Money => $monat['amount']->cents > $hoechster->cents
                ? $monat['amount']
                : $hoechster,
            Money::zero(),
        );
    }

    /**
     * Anteile am Gesamtbetrag, absteigend.
     *
     * @param  array<string, Money>  $nachKategorie
     * @return array<int, ForecastShare>
     */
    private function composition(array $nachKategorie, Money $gesamt): array
    {
        $anteile = collect($nachKategorie)
            ->map(fn (Money $betrag, string $label): array => [
                'label' => $label,
                'amount' => $betrag,
                'share' => $gesamt->isZero() ? 0.0 : round($betrag->cents / $gesamt->cents * 100, 1),
            ])
            ->sortByDesc(fn (array $anteil): int => $anteil['amount']->cents)
            ->values();

        return $anteile->all();
    }
}
