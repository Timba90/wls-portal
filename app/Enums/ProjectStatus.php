<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Status eines Projekts.
 */
enum ProjectStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Geplant',
            self::Active => 'Laufend',
            self::OnHold => 'Pausiert',
            self::Completed => 'Abgeschlossen',
            self::Cancelled => 'Abgebrochen',
            self::Archived => 'Archiviert',
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Active => 'ok',
            self::OnHold => 'warn',
            self::Cancelled => 'bad',
            self::Completed, self::Archived => 'mute',
        };
    }

    /**
     * Status, die in der Oberflaeche frei gewaehlt werden duerfen.
     *
     * Archiviert entsteht ausschliesslich ueber das Archivieren, damit der
     * Schreibschutz nicht versehentlich gesetzt oder aufgehoben wird.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return [self::Planned, self::Active, self::OnHold, self::Completed, self::Cancelled];
    }

    /**
     * Laeuft das Projekt noch? Bestimmt, was als offen gezaehlt wird.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planned, self::Active, self::OnHold], true);
    }
}
