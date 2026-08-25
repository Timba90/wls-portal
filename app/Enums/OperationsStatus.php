<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Ampel fuer Backup, Security und Updates eines Projekts.
 *
 * Wird von Hand gepflegt. „Unbekannt" ist die Voreinstellung und bewusst kein
 * Gruen: ein Projekt, das nie jemand geprueft hat, ist nicht in Ordnung — es
 * ist ungeprueft.
 */
enum OperationsStatus: string
{
    use HasOptions;

    case Ok = 'ok';
    case Attention = 'attention';
    case Critical = 'critical';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'In Ordnung',
            self::Attention => 'Prüfen',
            self::Critical => 'Kritisch',
            self::Unknown => 'Ungeprüft',
        };
    }

    /**
     * Kurzform fuer die Ampel in der Liste.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Attention => 'Prüfen',
            self::Critical => 'Kritisch',
            self::Unknown => '—',
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Ok => 'ok',
            self::Attention => 'warn',
            self::Critical => 'bad',
            self::Unknown => 'mute',
        };
    }

    /**
     * Zaehlt der Status als erledigt? Nur „In Ordnung" tut das.
     */
    public function isSettled(): bool
    {
        return $this === self::Ok;
    }
}
