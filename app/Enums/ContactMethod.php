<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Bevorzugte Kontaktart eines Ansprechpartners.
 */
enum ContactMethod: string
{
    use HasOptions;

    case Email = 'email';
    case Phone = 'phone';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-Mail',
            self::Phone => 'Telefon',
            self::Mobile => 'Mobil',
        };
    }
}
