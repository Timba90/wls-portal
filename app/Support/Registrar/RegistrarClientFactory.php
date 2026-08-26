<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;
use App\Models\IntegrationCredential;

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
        // Der Endpunkt ist kein Geheimnis und darf in der Konfiguration
        // stehen; Benutzername, Kennwort und Geheimnis kommen verschluesselt
        // aus der Datenbank (§50).
        /** @var array<string, mixed> $config */
        $config = config('services.'.$provider->configKey(), []);

        $config = array_merge($config, IntegrationCredential::valuesFor($provider));

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
