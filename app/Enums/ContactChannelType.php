<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Art eines Kontaktkanals — gilt für E-Mail-Adressen und Telefonnummern.
 */
enum ContactChannelType: string
{
    use HasOptions;

    case Business = 'business';
    case Private = 'private';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Geschäftlich',
            self::Private => 'Privat',
            self::Mobile => 'Mobil',
        };
    }

    /**
     * Mobil ist nur für Telefonnummern sinnvoll.
     *
     * @return array<int, self>
     */
    public static function forEmail(): array
    {
        return [self::Business, self::Private];
    }

    /**
     * @return array<int, self>
     */
    public static function forPhone(): array
    {
        return self::cases();
    }
}
