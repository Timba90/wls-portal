<?php

namespace App\Actions\Registrar;

use App\Models\Certificate;
use App\Models\Domain;
use App\Support\Registrar\RegistrarClient;
use App\Support\Registrar\RegistrarException;
use App\Support\Registrar\RemoteCertificate;
use App\Support\Registrar\RemoteDomain;
use Illuminate\Support\Facades\DB;

/**
 * Liest den Bestand eines Registrars ein.
 *
 * Ein Abgleich, kein Anlegen: was es schon gibt, wird aktualisiert. Der
 * Schluessel ist der Domainname beziehungsweise die Kennung des Zertifikats
 * beim Anbieter — beides bleibt ueber die Zeit stabil.
 *
 * Die Zuordnung zu Kunde und Kundenleistung wird nie ueberschrieben. Sie ist
 * von Hand gesetzt, und der Registrar weiss nichts davon; ein Import darf sie
 * nicht wieder wegwerfen.
 *
 * @phpstan-type ImportResult array{
 *     domains: array{new: int, updated: int},
 *     certificates: array{new: int, updated: int},
 *     skipped: int,
 * }
 */
class ImportRegistrarInventory
{
    /**
     * @param  bool  $dryRun  Nur zaehlen, nichts schreiben.
     * @return ImportResult
     */
    public function __invoke(RegistrarClient $client, bool $dryRun = false): array
    {
        if (! $client->isConfigured()) {
            throw new RegistrarException(sprintf(
                'Für %s sind keine Zugangsdaten hinterlegt. Ohne sie ist kein Import möglich.',
                $client->provider()->label(),
            ));
        }

        $ergebnis = [
            'domains' => ['new' => 0, 'updated' => 0],
            'certificates' => ['new' => 0, 'updated' => 0],
            'skipped' => 0,
        ];

        foreach ($client->domains() as $entfernt) {
            $vorhanden = $this->findDomain($client, $entfernt);

            $ergebnis['domains'][$vorhanden instanceof Domain ? 'updated' : 'new']++;

            if (! $dryRun) {
                $this->saveDomain($client, $entfernt, $vorhanden);
            }
        }

        foreach ($client->certificates() as $entfernt) {
            // Ohne Kennung des Anbieters gibt es keinen verlaesslichen
            // Schluessel: fuer denselben Namen kann es mehrere Zertifikate
            // geben. Lieber uebergehen als Dubletten anlegen.
            if ($entfernt->reference === null) {
                $ergebnis['skipped']++;

                continue;
            }

            $vorhanden = Certificate::query()
                ->where('provider', $client->provider())
                ->where('provider_reference', $entfernt->reference)
                ->first();

            $ergebnis['certificates'][$vorhanden instanceof Certificate ? 'updated' : 'new']++;

            if (! $dryRun) {
                $this->saveCertificate($client, $entfernt, $vorhanden);
            }
        }

        return $ergebnis;
    }

    /**
     * Sucht die vorhandene Domain — erst über die Kennung des Registrars, dann
     * über den Namen.
     *
     * Die Kennung ist der stabile Anker: benennt der Registrar eine Domain um
     * oder liefert er sie nach einem Transfer anders, bliebe beim Suchen nur
     * über den Namen der alte Datensatz stehen und ein zweiter entstünde.
     * Umgekehrt trägt ein von Hand angelegter Datensatz noch keine Kennung —
     * deshalb der Rückfall auf den Namen.
     */
    private function findDomain(RegistrarClient $client, RemoteDomain $entfernt): ?Domain
    {
        if ($entfernt->reference !== null) {
            $ueberKennung = Domain::query()
                ->where('provider', $client->provider())
                ->where('provider_reference', $entfernt->reference)
                ->first();

            if ($ueberKennung instanceof Domain) {
                return $ueberKennung;
            }
        }

        return Domain::query()->where('name', $entfernt->name)->first();
    }

    private function saveDomain(RegistrarClient $client, RemoteDomain $entfernt, ?Domain $vorhanden): void
    {
        DB::transaction(function () use ($client, $entfernt, $vorhanden): void {
            $domain = $vorhanden ?? new Domain;

            $domain->fill([
                'name' => $entfernt->name,
                'provider' => $client->provider(),
                'provider_reference' => $entfernt->reference,
                'status' => $entfernt->status,
                'registered_on' => $entfernt->registeredOn,
                'expires_on' => $entfernt->expiresOn,
                'auto_renew' => $entfernt->autoRenew,
                'nameservers' => $entfernt->nameservers,
                'synced_at' => now(),
            ]);

            $domain->save();
        });
    }

    private function saveCertificate(RegistrarClient $client, RemoteCertificate $entfernt, ?Certificate $vorhanden): void
    {
        DB::transaction(function () use ($client, $entfernt, $vorhanden): void {
            $zertifikat = $vorhanden ?? new Certificate;

            $zertifikat->fill([
                'common_name' => $entfernt->commonName,
                'provider' => $client->provider(),
                'provider_reference' => $entfernt->reference,
                'status' => $entfernt->status,
                'issuer' => $entfernt->issuer,
                'issued_on' => $entfernt->issuedOn,
                'expires_on' => $entfernt->expiresOn,
                'alternative_names' => $entfernt->alternativeNames,
                'synced_at' => now(),
            ]);

            $zertifikat->save();
        });
    }
}
