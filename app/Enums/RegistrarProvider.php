<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Anbieter, von dem Domains und Zertifikate stammen.
 */
enum RegistrarProvider: string
{
    use HasOptions;

    case Inwx = 'inwx';
    case DomainReselling = 'domain_reselling';

    public function label(): string
    {
        return match ($this) {
            self::Inwx => 'INWX',
            self::DomainReselling => 'Domain-Reselling',
        };
    }

    /**
     * Schluessel unter `config('services')`, unter dem die Zugangsdaten liegen.
     */
    public function configKey(): string
    {
        return match ($this) {
            self::Inwx => 'inwx',
            self::DomainReselling => 'domain_reselling',
        };
    }
}
