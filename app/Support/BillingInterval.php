<?php

namespace App\Support;

use App\Enums\BillingIntervalUnit;
use InvalidArgumentException;
use Stringable;

/**
 * Abrechnungsintervall aus Einheit und Anzahl.
 *
 * Beispiele: monatlich = month/1, quartalsweise = month/3, jaehrlich = year/1,
 * einmalig = once (ohne Anzahl).
 */
final readonly class BillingInterval implements Stringable
{
    private function __construct(
        public BillingIntervalUnit $unit,
        public ?int $count,
    ) {}

    public static function make(BillingIntervalUnit $unit, ?int $count = null): self
    {
        if (! $unit->isRecurring()) {
            return new self($unit, null);
        }

        $count ??= 1;

        if ($count < 1) {
            throw new InvalidArgumentException('Die Anzahl eines Abrechnungsintervalls muss mindestens 1 betragen.');
        }

        return new self($unit, $count);
    }

    public static function once(): self
    {
        return new self(BillingIntervalUnit::Once, null);
    }

    public static function monthly(): self
    {
        return self::make(BillingIntervalUnit::Month, 1);
    }

    public static function yearly(): self
    {
        return self::make(BillingIntervalUnit::Year, 1);
    }

    public function isRecurring(): bool
    {
        return $this->unit->isRecurring();
    }

    /**
     * Faktor, mit dem ein Betrag dieses Intervalls auf einen Monatswert
     * umgerechnet wird.
     *
     * 120 EUR jaehrlich ergibt so 10 EUR monatlich.
     */
    public function monthlyFactor(): float
    {
        if (! $this->isRecurring()) {
            return 0.0;
        }

        return 1 / ($this->unit->monthsPerUnit() * $this->count);
    }

    /**
     * Rechnet einen Betrag dieses Intervalls auf einen Monatswert um.
     */
    public function toMonthly(Money $amount): Money
    {
        return $amount->multipliedBy($this->monthlyFactor());
    }

    /**
     * Rechnet einen Betrag dieses Intervalls auf einen Jahreswert um.
     */
    public function toYearly(Money $amount): Money
    {
        return $amount->multipliedBy($this->monthlyFactor() * 12);
    }

    /**
     * Rechnet einen Betrag dieses Intervalls auf ein anderes um.
     *
     * Eine Domain fuer 15,00 EUR im Jahr kostet monatlich 1,25 EUR. Gerundet
     * wird kaufmaennisch auf ganze Cent; der Jahreswert kann dadurch um
     * wenige Cent abweichen, weil sich nicht jeder Betrag teilen laesst.
     *
     * Einmalige Betraege bleiben unveraendert: ein einmaliger Preis bezieht
     * sich auf keinen Zeitraum, den man umrechnen koennte.
     */
    public function convertTo(Money $amount, self $target): Money
    {
        if (! $this->isRecurring() || ! $target->isRecurring()) {
            return $amount;
        }

        return $amount->multipliedBy($this->monthlyFactor() / $target->monthlyFactor());
    }

    /**
     * Deutsche Bezeichnung, zum Beispiel "monatlich" oder "alle 3 Monate".
     */
    public function label(): string
    {
        if (! $this->isRecurring()) {
            return 'Einmalig';
        }

        if ($this->count === 1) {
            return match ($this->unit) {
                BillingIntervalUnit::Day => 'täglich',
                BillingIntervalUnit::Week => 'wöchentlich',
                BillingIntervalUnit::Month => 'monatlich',
                BillingIntervalUnit::Year => 'jährlich',
                BillingIntervalUnit::Once => 'Einmalig',
            };
        }

        if ($this->unit === BillingIntervalUnit::Month && $this->count === 3) {
            return 'quartalsweise';
        }

        if ($this->unit === BillingIntervalUnit::Month && $this->count === 6) {
            return 'halbjährlich';
        }

        return "alle {$this->count} ".mb_strtolower($this->unit->label());
    }

    public function __toString(): string
    {
        return $this->label();
    }
}
