<?php

namespace App\Enums;

/**
 * Zahlungsstand einer Rechnung, wie Flux ihn fuehrt (`orders.payment_state`).
 */
enum InvoicePaymentState: string
{
    case Open = 'open';
    case PartialPaid = 'partial_paid';
    case InPayment = 'in_payment';
    case Paid = 'paid';
    case Overpaid = 'overpaid';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::PartialPaid => 'Teilweise bezahlt',
            self::InPayment => 'In Zahlung',
            self::Paid => 'Bezahlt',
            self::Overpaid => 'Überzahlt',
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Open => 'warn',
            self::PartialPaid, self::InPayment => 'info',
            self::Paid, self::Overpaid => 'ok',
        };
    }

    public function isOutstanding(): bool
    {
        return in_array($this, [self::Open, self::PartialPaid], true);
    }
}
