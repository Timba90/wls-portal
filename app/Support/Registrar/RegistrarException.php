<?php

namespace App\Support\Registrar;

use RuntimeException;

/**
 * Fehler im Umgang mit einer Registrar-Schnittstelle.
 *
 * Traegt die Rohantwort mit, damit im Zweifel nachvollziehbar bleibt, was der
 * Anbieter tatsaechlich geschickt hat.
 */
class RegistrarException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|string|null  $payload
     */
    public function __construct(string $message, public readonly array|string|null $payload = null)
    {
        parent::__construct($message);
    }
}
