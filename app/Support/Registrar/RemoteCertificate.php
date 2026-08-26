<?php

namespace App\Support\Registrar;

use Carbon\CarbonImmutable;

/**
 * Ein Zertifikat, wie ein Anbieter es liefert.
 */
final readonly class RemoteCertificate
{
    /**
     * @param  array<int, string>  $alternativeNames
     */
    public function __construct(
        public string $commonName,
        public ?string $reference = null,
        public string $status = 'unknown',
        public ?string $issuer = null,
        public ?CarbonImmutable $issuedOn = null,
        public ?CarbonImmutable $expiresOn = null,
        public array $alternativeNames = [],
    ) {}
}
