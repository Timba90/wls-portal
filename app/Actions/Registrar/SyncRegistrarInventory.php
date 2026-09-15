<?php

namespace App\Actions\Registrar;

use App\Models\RegistrarSync;
use App\Support\Registrar\RegistrarClient;
use App\Support\Registrar\RegistrarException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ein Bestandsabgleich mit Protokoll.
 *
 * Der Import selbst steht in `ImportRegistrarInventory`; hier kommt dazu, was
 * ein unbeaufsichtigter Lauf braucht: jeder Versuch traegt sich ein, mit
 * Ergebnis oder mit der Meldung des Anbieters.
 *
 * Warum das nicht nebensaechlich ist: ein Abgleich, der nachts still
 * scheitert, sieht am naechsten Morgen aus wie ein Bestand ohne Aenderungen.
 * Erst das Protokoll macht den Unterschied sichtbar.
 *
 * Wiederholt wird nichts. Bei ResellerInterface verlaengert jeder weitere
 * Versuch eine Sperre; der naechste planmaessige Lauf ist frueh genug.
 */
class SyncRegistrarInventory
{
    public function __construct(private readonly ImportRegistrarInventory $import) {}

    /**
     * @param  'scheduled'|'manual'  $trigger
     */
    public function __invoke(RegistrarClient $client, string $trigger = 'scheduled'): RegistrarSync
    {
        $lauf = RegistrarSync::query()->create([
            'provider' => $client->provider(),
            'trigger' => $trigger,
            'started_at' => now(),
        ]);

        try {
            $ergebnis = ($this->import)($client);
        } catch (Throwable $ausnahme) {
            // Bewusst `Throwable` und nicht nur `RegistrarException`: ein
            // Datenbankfehler mitten im Abgleich waere sonst ein Lauf ohne
            // Ende und ohne Meldung — und wuerde die uebrigen Anbieter
            // mitreissen. Genau das soll dieses Protokoll verhindern.
            $meldung = $ausnahme instanceof RegistrarException
                ? $ausnahme->getMessage()
                : sprintf('Unerwarteter Fehler: %s', $ausnahme->getMessage());

            $lauf->forceFill([
                'finished_at' => now(),
                'error' => $meldung,
            ])->save();

            Log::warning('Bestandsabgleich fehlgeschlagen', [
                'anbieter' => $client->provider()->value,
                'meldung' => $meldung,
                // Bei einem unerwarteten Fehler ist die Spur das Wichtigste.
                'ausnahme' => $ausnahme instanceof RegistrarException ? null : $ausnahme,
            ]);

            return $lauf;
        }

        $lauf->forceFill([
            'finished_at' => now(),
            'domains_new' => $ergebnis['domains']['new'],
            'domains_updated' => $ergebnis['domains']['updated'],
            'certificates_new' => $ergebnis['certificates']['new'],
            'certificates_updated' => $ergebnis['certificates']['updated'],
            'skipped' => $ergebnis['skipped'],
        ])->save();

        return $lauf;
    }
}
