<?php

namespace App\Enums;

use App\Support\BillingInterval;

/**
 * Ausfuehrungsrhythmus einer Lexoffice-Serienrechnung.
 *
 * Die Werte entsprechen `recurringTemplateSettings.executionInterval` der
 * Lexoffice-API. `Biannually` heisst dort halbjaehrlich, nicht zweijaehrlich.
 */
enum LexofficeExecutionInterval: string
{
    case Weekly = 'WEEKLY';
    case Biweekly = 'BIWEEKLY';
    case Monthly = 'MONTHLY';
    case Bimonthly = 'BIMONTHLY';
    case Quarterly = 'QUARTERLY';
    case Biannually = 'BIANNUALLY';
    case Annually = 'ANNUALLY';

    public function label(): string
    {
        return $this->billingInterval()->label();
    }

    /**
     * Derselbe Rhythmus im Intervallmodell des Portals.
     */
    public function billingInterval(): BillingInterval
    {
        return match ($this) {
            self::Weekly => BillingInterval::make(BillingIntervalUnit::Week, 1),
            self::Biweekly => BillingInterval::make(BillingIntervalUnit::Week, 2),
            self::Monthly => BillingInterval::make(BillingIntervalUnit::Month, 1),
            self::Bimonthly => BillingInterval::make(BillingIntervalUnit::Month, 2),
            self::Quarterly => BillingInterval::make(BillingIntervalUnit::Month, 3),
            self::Biannually => BillingInterval::make(BillingIntervalUnit::Month, 6),
            self::Annually => BillingInterval::make(BillingIntervalUnit::Year, 1),
        };
    }
}
