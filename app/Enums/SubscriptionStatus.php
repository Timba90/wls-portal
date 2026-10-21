<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SubscriptionStatus: string
{
    use HasOptions;
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktiv', self::Suspended => 'Gesperrt', self::Disabled => 'Deaktiviert', self::Archived => 'Archiviert'
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Active => 'ok', self::Suspended => 'warn', self::Disabled => 'bad', self::Archived => 'mute'
        };
    }
}
