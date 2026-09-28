<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Was mit einem DNS-Eintrag geschehen soll.
 *
 * Drei Faelle, und keiner davon ist „die Zone neu schreiben": eine Aenderung
 * betrifft immer genau einen Eintrag.
 */
enum DnsChangeOperation: string
{
    use HasOptions;

    case Anlegen = 'anlegen';
    case Aendern = 'aendern';
    case Loeschen = 'loeschen';

    public function label(): string
    {
        return match ($this) {
            self::Anlegen => 'Eintrag anlegen',
            self::Aendern => 'Eintrag ändern',
            self::Loeschen => 'Eintrag löschen',
        };
    }

    /**
     * Braucht dieser Vorgang einen bestehenden Eintrag?
     */
    public function needsExisting(): bool
    {
        return $this !== self::Anlegen;
    }

    /**
     * Braucht dieser Vorgang einen Inhalt fuer den neuen Eintrag?
     */
    public function needsContent(): bool
    {
        return $this !== self::Loeschen;
    }
}
