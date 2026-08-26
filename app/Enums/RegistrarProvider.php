<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Anbieter, von dem Domains und Zertifikate stammen.
 *
 * Bewusst ein Enum und keine Tabelle: ein weiterer Anbieter bedeutet immer
 * auch einen neuen Anschluss in `app/Support/Registrar/`, also ohnehin Code.
 */
enum RegistrarProvider: string
{
    use HasOptions;

    case AutoDns = 'autodns';

    public function label(): string
    {
        return match ($this) {
            self::AutoDns => 'autoDNS',
        };
    }

    /**
     * Schluessel unter `config('services')`, unter dem der Endpunkt liegt.
     */
    public function configKey(): string
    {
        return match ($this) {
            self::AutoDns => 'autodns',
        };
    }
}
