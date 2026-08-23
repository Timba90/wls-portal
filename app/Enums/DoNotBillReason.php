<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Grund, aus dem eine Kundenleistung bewusst nicht abgerechnet wird.
 */
enum DoNotBillReason: string
{
    use HasOptions;

    case Included = 'included';
    case Goodwill = 'goodwill';
    case OwnService = 'own_service';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::Included => 'Inklusive',
            self::Goodwill => 'Kulanz',
            self::OwnService => 'Eigenleistung',
            self::Free => 'Kostenlos',
        };
    }
}
