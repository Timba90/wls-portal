<?php

use App\Enums\BillingIntervalUnit;
use App\Support\BillingInterval;
use App\Support\Money;

it('rechnet Betraege korrekt auf Monatswerte um', function (BillingInterval $interval, int $cents, int $monatlich): void {
    expect($interval->toMonthly(Money::fromCents($cents))->cents)->toBe($monatlich);
})->with([
    'monatlich bleibt monatlich' => [BillingInterval::monthly(), 4900, 4900],
    'jaehrlich wird gezwoelftelt' => [BillingInterval::yearly(), 12000, 1000],
    'quartalsweise wird gedrittelt' => [BillingInterval::make(BillingIntervalUnit::Month, 3), 15000, 5000],
    'halbjaehrlich wird halbiert' => [BillingInterval::make(BillingIntervalUnit::Month, 6), 12000, 2000],
    'zweijaehrlich' => [BillingInterval::make(BillingIntervalUnit::Year, 2), 24000, 1000],
]);

it('rechnet Betraege korrekt auf Jahreswerte um', function (): void {
    expect(BillingInterval::monthly()->toYearly(Money::fromCents(4900))->cents)->toBe(58800)
        ->and(BillingInterval::yearly()->toYearly(Money::fromCents(12000))->cents)->toBe(12000)
        ->and(BillingInterval::make(BillingIntervalUnit::Month, 3)->toYearly(Money::fromCents(15000))->cents)->toBe(60000);
});

it('rechnet taegliche und woechentliche Intervalle mit dem mittleren Jahr', function (): void {
    // 1 EUR taeglich entspricht 365,25 EUR jaehrlich.
    expect(BillingInterval::make(BillingIntervalUnit::Day, 1)->toYearly(Money::fromCents(100))->cents)->toBe(36525)
        // 10 EUR woechentlich entspricht 521,79 EUR jaehrlich.
        ->and(BillingInterval::make(BillingIntervalUnit::Week, 1)->toYearly(Money::fromCents(1000))->cents)->toBe(52179);
});

it('behandelt einmalige Leistungen nicht als wiederkehrend', function (): void {
    $einmalig = BillingInterval::once();

    expect($einmalig->isRecurring())->toBeFalse()
        ->and($einmalig->count)->toBeNull()
        ->and($einmalig->monthlyFactor())->toBe(0.0)
        ->and($einmalig->toMonthly(Money::fromCents(50000))->cents)->toBe(0)
        ->and($einmalig->toYearly(Money::fromCents(50000))->cents)->toBe(0);
});

it('ignoriert eine Anzahl bei einmaligen Leistungen', function (): void {
    expect(BillingInterval::make(BillingIntervalUnit::Once, 5)->count)->toBeNull();
});

it('setzt die Anzahl bei wiederkehrenden Intervallen auf mindestens 1', function (): void {
    expect(BillingInterval::make(BillingIntervalUnit::Month)->count)->toBe(1);

    expect(fn () => BillingInterval::make(BillingIntervalUnit::Month, 0))
        ->toThrow(InvalidArgumentException::class);
});

it('beschriftet Intervalle deutsch', function (BillingInterval $interval, string $label): void {
    expect($interval->label())->toBe($label);
})->with([
    [BillingInterval::once(), 'Einmalig'],
    [BillingInterval::monthly(), 'monatlich'],
    [BillingInterval::yearly(), 'jährlich'],
    [BillingInterval::make(BillingIntervalUnit::Month, 3), 'quartalsweise'],
    [BillingInterval::make(BillingIntervalUnit::Month, 6), 'halbjährlich'],
    [BillingInterval::make(BillingIntervalUnit::Week, 1), 'wöchentlich'],
    [BillingInterval::make(BillingIntervalUnit::Day, 1), 'täglich'],
    [BillingInterval::make(BillingIntervalUnit::Month, 4), 'alle 4 monate'],
    [BillingInterval::make(BillingIntervalUnit::Year, 2), 'alle 2 jahre'],
]);
