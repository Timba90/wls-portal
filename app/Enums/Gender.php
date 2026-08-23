<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Gender: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';
    case Diverse = 'diverse';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Männlich',
            self::Female => 'Weiblich',
            self::Diverse => 'Divers',
        };
    }
}
