<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CustomerType: string
{
    use HasOptions;

    case Company = 'company';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Unternehmen',
            self::Private => 'Privatperson',
        };
    }
}
