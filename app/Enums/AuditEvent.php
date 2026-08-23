<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Art einer protokollierten Aenderung.
 */
enum AuditEvent: string
{
    use HasOptions;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Archived = 'archived';
    case Restored = 'restored';
    case Attached = 'attached';
    case Detached = 'detached';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Angelegt',
            self::Updated => 'Geändert',
            self::Deleted => 'Gelöscht',
            self::Archived => 'Archiviert',
            self::Restored => 'Reaktiviert',
            self::Attached => 'Hinzugefügt',
            self::Detached => 'Entfernt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created, self::Attached => 'green',
            self::Updated => 'blue',
            self::Deleted, self::Detached => 'red',
            self::Archived => 'amber',
            self::Restored => 'purple',
        };
    }
}
