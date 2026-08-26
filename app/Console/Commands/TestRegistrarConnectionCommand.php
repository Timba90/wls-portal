<?php

namespace App\Console\Commands;

use App\Enums\RegistrarProvider;
use App\Support\Registrar\RegistrarClient;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use Illuminate\Console\Command;

/**
 * Prueft die Zugaenge der Registrare.
 *
 * Der kleinste moegliche Aufruf: er liest nichts und aendert nichts, sondern
 * beantwortet nur, ob Zugangsdaten und Kontext stimmen. Sinnvoll als erster
 * Schritt nach dem Hinterlegen — und als schnelle Antwort, wenn ein Import
 * mit einer Anmeldemeldung abbricht.
 */
class TestRegistrarConnectionCommand extends Command
{
    protected $signature = 'registrar:test {anbieter? : autodns; ohne Angabe alle eingerichteten}';

    protected $description = 'Zugang zu den Registraren prüfen';

    public function handle(RegistrarClientFactory $factory): int
    {
        $anschluesse = $this->clients($factory);

        if ($anschluesse === null) {
            return self::FAILURE;
        }

        if ($anschluesse === []) {
            $this->components->error('Kein Anbieter ist eingerichtet. Zugangsdaten werden unter „Schnittstellen" gepflegt.');

            return self::FAILURE;
        }

        $fehler = false;

        foreach ($anschluesse as $client) {
            try {
                $this->components->info(sprintf('%s: %s', $client->provider()->label(), $client->testConnection()));
            } catch (RegistrarException $ausnahme) {
                $fehler = true;
                $this->components->error(sprintf('%s: %s', $client->provider()->label(), $ausnahme->getMessage()));
            }
        }

        return $fehler ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Die gewaehlten Anschluesse — oder `null`, wenn das Argument keinen
     * Anbieter benennt. Ein Tippfehler im Namen ist etwas anderes als ein
     * Anbieter ohne Zugangsdaten, und beides verdient seine eigene Meldung.
     *
     * @return array<int, RegistrarClient>|null
     */
    private function clients(RegistrarClientFactory $factory): ?array
    {
        $gewaehlt = $this->argument('anbieter');

        if ($gewaehlt === null) {
            return $factory->configured();
        }

        $anbieter = RegistrarProvider::tryFrom((string) $gewaehlt);

        if (! $anbieter instanceof RegistrarProvider) {
            $this->components->error(sprintf(
                'Unbekannter Anbieter „%s". Möglich sind: %s.',
                $gewaehlt,
                implode(', ', array_column(RegistrarProvider::cases(), 'value')),
            ));

            return null;
        }

        return [$factory->for($anbieter)];
    }
}
