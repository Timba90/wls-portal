<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PleskServer: string
{
    use HasOptions;
    case Cloud01 = 'cloud01';
    case Cloud02 = 'cloud02';
    case Cloud03 = 'cloud03';

    public function label(): string
    {
        return $this->value;
    }
}
