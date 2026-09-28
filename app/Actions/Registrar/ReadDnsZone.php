<?php

namespace App\Actions\Registrar;

use App\Models\Domain;
use App\Support\Registrar\DnsZone;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use Illuminate\Support\Facades\Cache;

/**
 * Liest die DNS-Zone einer Domain beim Anbieter.
 *
 * Nur auf Zuruf: aufgerufen wird das hier, wenn jemand die Zone auf der
 * Detailseite aufschlaegt. Nichts davon laeuft nebenher, nichts landet in der
 * Datenbank — die Zone gehoert dem Anbieter, und eine Kopie waere nur ein
 * zweiter, aelterer Stand.
 *
 * Der Zwischenspeicher ist keine Beschleunigung, sondern Ruecksicht: ein
 * Wechsel zwischen den Reitern soll nicht jedes Mal beim Anbieter anklopfen.
 */
class ReadDnsZone
{
    public function __construct(private readonly RegistrarClientFactory $factory) {}

    /**
     * @param  bool  $force  Verwirft den zwischengespeicherten Stand und liest neu.
     *
     * @throws RegistrarException
     */
    public function __invoke(Domain $domain, bool $force = false): DnsZone
    {
        /*
         * Von Hand gepflegte Domains haben keinen Anschluss. Die Frage nach
         * ihrer Zone hat damit keinen Adressaten — und sie wird gestellt,
         * bevor die Fabrik einen Anschluss bauen soll, den es nicht gibt.
         */
        if (! $domain->provider->hasClient()) {
            throw new RegistrarException(
                'Diese Domain wird von Hand gepflegt: es gibt keinen Anschluss, der ihre DNS-Zone lesen könnte.',
            );
        }

        $client = $this->factory->for($domain->provider);

        if (! $client->canReadZone()) {
            throw new RegistrarException(sprintf(
                'Der Anschluss %s kann keine DNS-Zone lesen.',
                $domain->provider->label(),
            ));
        }

        if (! $client->isConfigured()) {
            throw new RegistrarException(sprintf(
                'Für %s sind keine Zugangsdaten hinterlegt.',
                $domain->provider->label(),
            ));
        }

        $schluessel = $this->cacheKey($domain);

        if ($force) {
            Cache::forget($schluessel);
        }

        $minuten = max(0, (int) config('portal.dns.cache_minutes', 10));

        /*
         * Ein Fehlschlag wird nicht zwischengespeichert: `remember` legt nur
         * ab, was die Funktion zurueckgibt, und eine Ausnahme kommt hier
         * unveraendert durch. Andernfalls haette eine einzelne Stoerung beim
         * Anbieter fuer die volle Dauer Bestand.
         */
        if ($minuten === 0) {
            return $client->zone($domain->name);
        }

        return Cache::remember(
            $schluessel,
            now()->addMinutes($minuten),
            fn (): DnsZone => $client->zone($domain->name),
        );
    }

    /**
     * Der Anbieter gehoert in den Schluessel: wechselt eine Domain den
     * Anschluss, ist der alte Stand nicht mehr gemeint.
     */
    public function cacheKey(Domain $domain): string
    {
        return sprintf('registrar.dns.%s.%s', $domain->provider->value, mb_strtolower($domain->name));
    }
}
