<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Salutation: string
{
    use HasOptions;

    case Herr = 'herr';
    case Frau = 'frau';
    case Neutral = 'neutral';

    public function label(): string
    {
        return match ($this) {
            self::Herr => 'Herr',
            self::Frau => 'Frau',
            self::Neutral => 'Ohne Anrede',
        };
    }

    /**
     * Anredezeile für Briefe und E-Mails.
     */
    public function salutationLine(): string
    {
        return match ($this) {
            self::Herr => 'Sehr geehrter Herr',
            self::Frau => 'Sehr geehrte Frau',
            self::Neutral => 'Guten Tag',
        };
    }
}
