<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TicketPriority: string
{
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Niedrig',
            self::Normal => 'Normal',
            self::High => 'Hoch',
            self::Urgent => 'Dringend',
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Low => 'mute',
            self::Normal => 'info',
            self::High => 'warn',
            self::Urgent => 'bad',
        };
    }

    /**
     * Gewicht für die Sortierung: dringende Tickets zuerst.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 3,
            self::Normal => 2,
            self::Low => 1,
        };
    }
}
