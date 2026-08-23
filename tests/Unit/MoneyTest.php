<?php

use App\Support\Money;

it('speichert Betraege als ganze Cent', function (): void {
    expect(Money::fromCents(4999)->cents)->toBe(4999)
        ->and(Money::fromCents(4999)->toEuro())->toBe(49.99);
});

it('liest deutsche und englische Eingaben', function (string|int|float|null $input, int $cents): void {
    expect(Money::fromEuroInput($input)->cents)->toBe($cents);
})->with([
    ['49,99', 4999],
    ['49.99', 4999],
    ['1.234,56', 123456],
    ['1234.56', 123456],
    ['0', 0],
    ['', 0],
    [null, 0],
    [59, 5900],
    [12.5, 1250],
]);

it('lehnt unsinnige Eingaben ab', function (): void {
    expect(fn () => Money::fromEuroInput('keine Zahl'))
        ->toThrow(InvalidArgumentException::class);
});

it('formatiert Betraege deutsch', function (): void {
    expect(Money::fromCents(123456)->format())->toBe('1.234,56 €')
        ->and(Money::fromCents(4999)->format(withCurrency: false))->toBe('49,99')
        ->and(Money::fromCents(-500)->format())->toBe('-5,00 €')
        ->and(Money::fromCents(123456)->toInput())->toBe('1234,56');
});

it('rechnet ohne Rundungsfehler', function (): void {
    expect(Money::fromCents(4999)->plus(Money::fromCents(1))->cents)->toBe(5000)
        ->and(Money::fromCents(5900)->minus(Money::fromCents(4900))->cents)->toBe(1000)
        ->and(Money::fromCents(12000)->multipliedBy(1 / 12)->cents)->toBe(1000)
        ->and(Money::fromCents(10000)->multipliedBy(1 / 3)->cents)->toBe(3333);
});
