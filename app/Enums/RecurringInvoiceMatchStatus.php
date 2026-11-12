<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Ergebnis des Abgleichs zwischen Kundenleistung und Lexoffice-Serienrechnung.
 */
enum RecurringInvoiceMatchStatus: string
{
    use HasOptions;

    case Matching = 'matching';
    case AmountDeviation = 'amount_deviation';
    case IntervalDeviation = 'interval_deviation';
    case NotInvoiced = 'not_invoiced';
    case InvoicedButNotBillable = 'invoiced_but_not_billable';

    public function label(): string
    {
        return match ($this) {
            self::Matching => 'Stimmig',
            self::AmountDeviation => 'Betrag weicht ab',
            self::IntervalDeviation => 'Intervall weicht ab',
            self::NotInvoiced => 'Keine Serienrechnung',
            self::InvoicedButNotBillable => 'Abgerechnet, aber nicht abrechenbar',
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Matching => 'ok',
            self::AmountDeviation, self::IntervalDeviation => 'warn',
            self::NotInvoiced, self::InvoicedButNotBillable => 'bad',
        };
    }

    public function isDeviation(): bool
    {
        return $this !== self::Matching;
    }
}
