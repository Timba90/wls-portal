<?php

namespace App\Console\Commands;

use App\Actions\Registrar\SyncRegistrarInventory;
use App\Support\Registrar\RegistrarClientFactory;
use Illuminate\Console\Command;

/**
 * Der taegliche Abgleich (§60).
 *
 * Laeuft ueber alle eingerichteten Anbieter und traegt jeden Versuch ins
 * Protokoll ein. Der Zeitplan ruft ihn mit `--geplant` auf; ohne die Angabe
 * gilt der Lauf als von Hand angestossen, denn dann hat ihn jemand getippt. Anders als `registrar:import` fragt er nichts und gibt
 * knapp aus — er ist fuer den Zeitplan gedacht.
 *
 * Ein Fehlschlag bei einem Anbieter haelt die uebrigen nicht auf; der
 * Rueckgabewert meldet ihn trotzdem, damit der Zeitplan ihn sieht.
 */
class SyncRegistrarInventoryCommand extends Command
{
    protected $signature = 'registrar:sync {--geplant : Kennzeichnet den Lauf im Protokoll als planmäßig}';

    protected $description = 'Gleicht den Domainbestand aller eingerichteten Anbieter ab';

    public function handle(RegistrarClientFactory $factory, SyncRegistrarInventory $sync): int
    {
        $anschluesse = $factory->configured();

        if ($anschluesse === []) {
            $this->components->info('Kein Anbieter eingerichtet — nichts abzugleichen.');

            return self::SUCCESS;
        }

        $fehler = false;

        foreach ($anschluesse as $client) {
            $lauf = $sync($client, trigger: $this->option('geplant') ? 'scheduled' : 'manual');

            if ($lauf->isFailed()) {
                $fehler = true;
                $this->components->error(sprintf('%s: %s', $client->provider()->label(), $lauf->error));

                continue;
            }

            $this->components->info(sprintf(
                '%s: %d neu, %d geändert, %d übergangen.',
                $client->provider()->label(),
                $lauf->domains_new + $lauf->certificates_new,
                $lauf->domains_updated + $lauf->certificates_updated,
                $lauf->skipped,
            ));
        }

        return $fehler ? self::FAILURE : self::SUCCESS;
    }
}
