<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Art einer Projektposition.
 *
 * Der Entwurf unterscheidet in der Spalte „Art\" zwischen einmaligen und
 * wiederkehrenden Positionen. Das entscheidet, ob die Position in das
 * Projektvolumen einfliesst oder als laufender Betrag daneben steht.
 */
enum ProjectPositionKind: string
{
    use HasOptions;

    case OneTime = 'one_time';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Einmalig',
            self::Recurring => 'Wiederkehrend',
        };
    }
}
