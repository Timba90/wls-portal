<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Einheit eines Abrechnungsintervalls.
 *
 * Das Intervall besteht aus Einheit und Anzahl, damit spaeter weitere
 * Rhythmen moeglich bleiben, ohne das Datenmodell zu aendern:
 * quartalsweise ist `month` mit Anzahl 3.
 */
enum BillingIntervalUnit: string
{
    use HasOptions;

    case Once = 'once';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Einmalig',
            self::Day => 'Tage',
            self::Week => 'Wochen',
            self::Month => 'Monate',
            self::Year => 'Jahre',
        };
    }

    /**
     * Einmalige Leistungen wiederholen sich nicht und fliessen deshalb nicht in
     * Monats- und Jahreskennzahlen ein.
     */
    public function isRecurring(): bool
    {
        return $this !== self::Once;
    }

    public function requiresCount(): bool
    {
        return $this->isRecurring();
    }

    /**
     * Anzahl der Monate, die eine Einheit umfasst.
     *
     * Grundlage ist das mittlere Jahr mit 365,25 Tagen.
     */
    public function monthsPerUnit(): float
    {
        return match ($this) {
            self::Once => 0.0,
            self::Day => 12 / 365.25,
            self::Week => 12 / (365.25 / 7),
            self::Month => 1.0,
            self::Year => 12.0,
        };
    }
}
