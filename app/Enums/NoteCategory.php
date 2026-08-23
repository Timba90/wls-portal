<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kategorie einer Notiz.
 *
 * Als String gespeichert, damit weitere Kategorien ohne Migration ergaenzt
 * werden koennen.
 */
enum NoteCategory: string
{
    use HasOptions;

    case General = 'general';
    case Technical = 'technical';
    case Billing = 'billing';
    case Contract = 'contract';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Allgemein',
            self::Technical => 'Technik',
            self::Billing => 'Abrechnung',
            self::Contract => 'Vertrag',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::General => 'gray',
            self::Technical => 'blue',
            self::Billing => 'amber',
            self::Contract => 'purple',
        };
    }
}
