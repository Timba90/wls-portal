<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Status eines Katalogartikels oder einer Artikelvariante.
 */
enum CatalogStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktiv',
            self::Archived => 'Archiviert',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Archived => 'gray',
        };
    }
}
