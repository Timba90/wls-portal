<?php

namespace App\Actions\Registrar;

use App\Exceptions\ReadOnlyRecordException;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;

/**
 * Aendert eine von Hand gepflegte Domain.
 *
 * Die Regel dieser Aktion ist ihre Grenze: eine *importierte* Domain aendert
 * sie nicht. Deren technischer Stand kommt vom Anbieter, und der naechste
 * Abgleich wuerde eine Eingabe hier ohnehin ueberschreiben — ein Feld, das
 * stillschweigend zurueckspringt, ist schlimmer als keins.
 *
 * Der Anbieter selbst bleibt unveraendert: aus einer von Hand gepflegten Domain
 * wird keine importierte. Diesen Wechsel macht der Abgleich, wenn die Domain
 * eines Tages im Bestand eines angeschlossenen Registrars auftaucht — er findet
 * sie ueber den Namen und uebernimmt sie samt Zuordnung.
 */
class UpdateManualDomain
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Domain $domain, array $attributes): Domain
    {
        $this->guardAgainstImported($domain);

        return DB::transaction(function () use ($domain, $attributes): Domain {
            $domain->fill([
                'name' => Domain::normalizeName((string) $attributes['name']),
                'status' => $attributes['status'] ?? $domain->status,
                'registered_on' => $attributes['registered_on'] ?? null,
                'expires_on' => $attributes['expires_on'] ?? null,
                'auto_renew' => $attributes['auto_renew'] ?? false,
                'nameservers' => $attributes['nameservers'] ?? [],
            ]);

            $domain->save();

            return $domain;
        });
    }

    private function guardAgainstImported(Domain $domain): void
    {
        if ($domain->isMaintainedByHand()) {
            return;
        }

        throw new ReadOnlyRecordException(sprintf(
            'Diese Domain kommt aus dem Bestand von %s. Ihr technischer Stand wird dort geändert — '
            .'der nächste Abgleich würde eine Eingabe hier ohnehin überschreiben.',
            $domain->provider->label(),
        ));
    }
}
