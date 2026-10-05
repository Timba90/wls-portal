<?php

namespace App\Enums;

enum FluxLedgerAccountType: string
{
    case Asset = 'asset';
    case Expense = 'expense';
    case Liability = 'liability';
    case Revenue = 'revenue';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Aktiva', self::Expense => 'Aufwand',
            self::Liability => 'Passiva', self::Revenue => 'Erlöse',
        };
    }
}
