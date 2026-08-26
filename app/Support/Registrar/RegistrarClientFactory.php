<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;

/**
 * Liefert den Anschluss zu einem Anbieter.
 *
 * Eine Stelle, an der Anbieter und Zugangsdaten zusammenfinden — damit der
 * Rest der Anwendung nur die Schnittstelle kennt und nicht die Anbieter.
 */
class RegistrarClientFactory
{
    public function for(RegistrarProvider $provider): RegistrarClient
    {
        /** @var array<string, mixed> $config */
        $config = config('services.'.$provider->configKey(), []);

        return match ($provider) {
            RegistrarProvider::Inwx => new InwxClient($config),
            RegistrarProvider::DomainReselling => new DomainResellingClient($config),
        };
    }

    /**
     * Alle Anbieter, deren Zugangsdaten hinterlegt sind.
     *
     * @return array<int, RegistrarClient>
     */
    public function configured(): array
    {
        return array_values(array_filter(
            array_map(fn (RegistrarProvider $anbieter): RegistrarClient => $this->for($anbieter), RegistrarProvider::cases()),
            fn (RegistrarClient $client): bool => $client->isConfigured(),
        ));
    }
}
