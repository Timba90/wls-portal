<?php

namespace App\Support\Registrar;

use Carbon\CarbonImmutable;

/**
 * Eine Domain, wie ein Registrar sie liefert.
 *
 * Bewusst ein eigener Typ und kein Array: was aus einer fremden Schnittstelle
 * kommt, soll genau einmal geprueft werden — hier — und danach im Rest der
 * Anwendung verlaesslich sein.
 */
final readonly class RemoteDomain
{
    /**
     * @param  array<int, string>  $nameservers
     */
    public function __construct(
        public string $name,
        public ?string $reference = null,
        public string $status = 'unknown',
        public ?CarbonImmutable $registeredOn = null,
        public ?CarbonImmutable $expiresOn = null,
        public bool $autoRenew = false,
        public array $nameservers = [],
    ) {}
}
