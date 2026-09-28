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
        // Zu `Manual` gehoert kein Anschluss. Wer hierher kommt, hat vorher
        // nicht gefragt — deshalb eine klare Meldung statt eines halben
        // Clients, der bei jedem Aufruf scheitert.
        if (! $provider->hasClient()) {
            throw new RegistrarException(sprintf(
                'Zu „%s" gehört kein Anschluss: solche Domains werden hier von Hand gepflegt.',
                $provider->label(),
            ));
        }

        // Endpunkt und Kontext sind keine Geheimnisse und stehen deshalb in
        // der Konfiguration; Benutzername und Kennwort kommen verschluesselt
        // aus der Datenbank (§50) und gehen einem gleichnamigen Wert aus der
        // Konfiguration vor.
        /** @var array<string, mixed> $config */
        $config = config('services.'.$provider->configKey(), []);

        $config = array_merge($config, IntegrationCredential::valuesFor($provider));

        return match ($provider) {
            RegistrarProvider::AutoDns => new AutoDnsClient($config),
            RegistrarProvider::ResellerInterface => new ResellerInterfaceClient($config),
            RegistrarProvider::Manual => throw new RegistrarException(
                'Zu von Hand gepflegten Domains gehört kein Anschluss.',
            ),
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
            array_map(fn (RegistrarProvider $anbieter): RegistrarClient => $this->for($anbieter), RegistrarProvider::withClient()),
            fn (RegistrarClient $client): bool => $client->isConfigured(),
        ));
    }
}
