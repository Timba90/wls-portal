<?php

namespace App\Console\Commands;

use App\Actions\Registrar\ImportRegistrarInventory;
use App\Enums\RegistrarProvider;
use App\Support\Registrar\RegistrarClient;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use Illuminate\Console\Command;

/**
 * Liest Domains und Zertifikate der Registrare ein.
 *
 * Der Trockenlauf ist der vorgesehene erste Schritt: er zeigt, was der Import
 * anlegen und aendern wuerde, ohne etwas zu schreiben. Die Feldnamen der
 * Schnittstellen sind gegen die Dokumentation geschrieben und noch nicht gegen
 * ein echtes Konto geprueft — der Trockenlauf ist die Gelegenheit, das zu tun.
 */
class ImportRegistrarInventoryCommand extends Command
{
    protected $signature = 'registrar:import
                            {anbieter? : autodns oder resellerinterface; ohne Angabe alle eingerichteten}
                            {--trocken : Nur zeigen, was geschehen würde}';

    protected $description = 'Domains und Zertifikate der Registrare einlesen';

    public function handle(RegistrarClientFactory $factory, ImportRegistrarInventory $import): int
    {
        $anschluesse = $this->clients($factory);

        if ($anschluesse === null) {
            return self::FAILURE;
        }

        if ($anschluesse === []) {
            $this->components->error('Kein Anbieter ist eingerichtet. Zugangsdaten werden unter „Schnittstellen" gepflegt.');

            return self::FAILURE;
        }

        $trocken = (bool) $this->option('trocken');
        $fehler = false;

        foreach ($anschluesse as $client) {
            $this->components->info(sprintf(
                '%s wird eingelesen%s',
                $client->provider()->label(),
                $trocken ? ' (Trockenlauf, es wird nichts geschrieben)' : '',
            ));

            try {
                $ergebnis = $import($client, $trocken);
            } catch (RegistrarException $ausnahme) {
                $fehler = true;
                $this->components->error($ausnahme->getMessage());

                if ($ausnahme->payload !== null) {
                    $roh = is_string($ausnahme->payload)
                        ? $ausnahme->payload
                        : (json_encode($ausnahme->payload, JSON_UNESCAPED_UNICODE) ?: '');

                    $this->line('  Rohantwort: '.mb_substr($roh, 0, 500));
                }

                continue;
            }

            $this->table(
                ['Bestand', 'Neu', 'Geändert'],
                [
                    ['Domains', $ergebnis['domains']['new'], $ergebnis['domains']['updated']],
                    ['Zertifikate', $ergebnis['certificates']['new'], $ergebnis['certificates']['updated']],
                ],
            );

            if ($ergebnis['skipped'] > 0) {
                $this->components->warn(sprintf(
                    '%d Zertifikate ohne Kennung des Anbieters übergangen — ohne sie gäbe es keinen eindeutigen Schlüssel.',
                    $ergebnis['skipped'],
                ));
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

        $client = $factory->for($anbieter);

        return $client->isConfigured() ? [$client] : [];
    }
}
