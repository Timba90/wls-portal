<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Status eines Projekt-Meilensteins.
 */
enum MilestoneStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::InProgress => 'In Arbeit',
            self::Done => 'Erledigt',
            self::Skipped => 'Entfallen',
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warn',
            self::Done => 'ok',
            self::Skipped => 'mute',
        };
    }

    /**
     * Zaehlt der Meilenstein als abgeschlossen?
     *
     * Entfallene Meilensteine zaehlen mit: sie stehen dem Fortschritt nicht
     * mehr im Weg und wuerden ihn sonst dauerhaft unter 100 Prozent halten.
     */
    public function countsAsSettled(): bool
    {
        return in_array($this, [self::Done, self::Skipped], true);
    }
}
