<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Status einer Kundenleistung.
 */
enum CustomerServiceStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Geplant',
            self::Active => 'Aktiv',
            self::Paused => 'Pausiert',
            self::Ended => 'Beendet',
            self::Archived => 'Archiviert',
        };
    }

    /**
     * Ausprägung der Statusplakette aus dem Entwurf.
     */
    public function pillKind(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Active => 'ok',
            self::Paused => 'warn',
            self::Ended, self::Archived => 'mute',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'blue',
            self::Active => 'green',
            self::Paused => 'amber',
            self::Ended => 'gray',
            self::Archived => 'gray',
        };
    }

    /**
     * Nur aktive Leistungen fliessen in Umsatz-, Kosten- und Margenkennzahlen
     * ein.
     */
    public function countsTowardsRevenue(): bool
    {
        return $this === self::Active;
    }

    /**
     * Archivierte Leistungen sind vollstaendig schreibgeschuetzt.
     */
    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }

    /**
     * Status, die in der Oberflaeche direkt gewaehlt werden koennen.
     * Archiviert wird ausschliesslich ueber die Archivierungsaktion gesetzt.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return [self::Planned, self::Active, self::Paused, self::Ended];
    }
}
