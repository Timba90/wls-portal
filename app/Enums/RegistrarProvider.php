<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Anbieter, von dem Domains und Zertifikate stammen.
 *
 * Bewusst ein Enum und keine Tabelle: ein weiterer Anbieter mit Schnittstelle
 * bedeutet immer auch einen neuen Anschluss in `app/Support/Registrar/`, also
 * ohnehin Code.
 *
 * Ein Fall faellt aus der Reihe: `Manual` steht fuer eine Domain, die von Hand
 * gepflegt wird, weil ihr Registrar hier keine Schnittstelle hat (§60: „Domains
 * anderer Provider muessen ebenfalls manuell verwaltbar sein"). Zu ihm gehoert
 * kein Anschluss — was ihn braucht, fragt vorher `hasClient()`.
 */
enum RegistrarProvider: string
{
    use HasOptions;

    case AutoDns = 'autodns';
    case ResellerInterface = 'resellerinterface';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::AutoDns => 'autoDNS',
            self::ResellerInterface => 'ResellerInterface (do.de)',
            self::Manual => 'Von Hand gepflegt',
        };
    }

    /**
     * Schluessel unter `config('services')`, unter dem der Endpunkt liegt.
     */
    public function configKey(): string
    {
        return match ($this) {
            self::AutoDns => 'autodns',
            self::ResellerInterface => 'resellerinterface',
            // Unter diesem Schluessel liegt nichts, und es soll auch nichts
            // dort liegen: von Hand gepflegte Domains haben keinen Endpunkt.
            self::Manual => 'manual',
        };
    }

    /**
     * Gehoert zu diesem Anbieter ein Anschluss, der seinen Bestand liest?
     *
     * Die Frage steht vor jedem Zugriff auf `RegistrarClientFactory`: fuer
     * `Manual` gibt es keinen Anschluss, und der Versuch, einen zu bauen, ist
     * ein Programmfehler und kein Betriebsfall.
     */
    public function hasClient(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * Die Anbieter mit Anschluss.
     *
     * Ueberall dort gemeint, wo es um Zugangsdaten, Verbindungstests oder den
     * Import geht — nicht in Filtern einer Liste, denn nach „von Hand
     * gepflegt" filtert man sehr wohl.
     *
     * @return array<int, self>
     */
    public static function withClient(): array
    {
        return array_values(array_filter(self::cases(), fn (self $anbieter): bool => $anbieter->hasClient()));
    }
}
