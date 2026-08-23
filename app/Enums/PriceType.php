<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Art eines Preises im Preisverlauf.
 */
enum PriceType: string
{
    use HasOptions;

    case Sales = 'sales';
    case Purchase = 'purchase';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Verkaufspreis',
            self::Purchase => 'Einkaufspreis',
        };
    }

    /**
     * Spalte der Kundenleistung, die diesen Preis haelt.
     */
    public function column(): string
    {
        return match ($this) {
            self::Sales => 'sales_price_cents',
            self::Purchase => 'purchase_price_cents',
        };
    }
}
