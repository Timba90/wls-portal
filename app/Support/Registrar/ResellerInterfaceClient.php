<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Direkter Anschluss an ResellerInterface (do.de).
 *
 * Das Portal meldet sich selbst bei `core.resellerinterface.de` an — die
 * Zugangsdaten kommen verschluesselt aus `integration_credentials`. Die
 * fruehere Variante rief eine Bruecke auf demselben Host auf; die ist jetzt
 * ueberfluessig, weil Anmeldung und Sitzung hier mitlaufen.
 *
 * Das Protokoll (am offiziellen Client `resellerinterface/api-client-php`
 * gelesen, nicht geraten):
 *
 * - Anmeldung: `POST stable/reseller/login` mit `username`, `password`,
 *   optional `resellerId` (Formular-Felder, nicht JSON).
 * - Die Sitzung kehrt als Cookie `coreSID` zurueck und geht als eben
 *   solchen wieder hinaus.
 * - Aufrufe: `POST stable/{kategorie}/{funktion}` mit Formular-Feldern.
 * - Jede Antwort ist ein JSON-Umschlag: `success`, `state`, `stateName`
 *   und die Nutzdaten unter `data`.
 *
 * Die Feldnamen der einzelnen Aufrufe stammen aus der offiziellen
 * OpenAPI-Beschreibung des Anbieters („CoreAPI - Reseller-Interface", 1.0).
 * Dort stehen `domain/list`, `tls/list` und `dns/getZoneDetails` mit ihren
 * Parametern und Antwortfeldern; geraten ist hier nichts.
 *
 * Warum so streng: ein selbstgebautes Login-Skript hat sich mit leeren
 * Zugangsdaten dutzendfach angemeldet und das Konto gesperrt; DNS-Aenderungen
 * waren danach fuer alle Kunden blockiert. Daraus folgen Regeln, die hier im
 * Code stehen und nicht nur im Kommentar:
 *
 * 1. Nur lesende Aufrufe. Schreibende Aktionen sind gar nicht erst erreichbar.
 * 2. Eine Anmeldung je 15 Minuten, nicht je Aufruf: die `coreSID` liegt im
 *    Cache — genau so lange, wie die fruehere Bruecke ihre Sitzung hielt.
 *    Ein Login je Anfrage hat das Konto schon einmal gesperrt.
 * 3. Kein Wiederholen. Ein fehlgeschlagener Aufruf wird nie ein zweites Mal
 *    versucht — jeder weitere Versuch verlaengert eine Sperre. Einzige
 *    Ausnahme: eine abgelaufene Sitzung meldet sich einmal neu an.
 * 4. Bei `TOO_MANY_ATTEMPTS` oder `WRONG_USERNAME_OR_PASSWORD` bricht der
 *    Anschluss mit einer unmissverstaendlichen Meldung ab.
 */
class ResellerInterfaceClient implements RegistrarClient
{
    /**
     * Aktionen, die dieser Anschluss aufrufen darf — ausschliesslich lesende.
     *
     * Eine Positivliste statt einer Sperrliste: was nicht draufsteht, geht
     * nicht. `domain/transfer` mit einem Testnamen hat schon einmal einen
     * echten Transfer ausgeloest.
     */
    private const ERLAUBTE_AKTIONEN = [
        'domain/list',
        'domain/check',
        'tld/list',
        'tls/list',
        'dns/getZoneDetails',
    ];

    /**
     * Ohne Angabe liefert `domain/list` nur 25 Eintraege. Das ist kein Fehler
     * des Anbieters, sondern seine Voreinstellung — ohne dieses Limit fehlen
     * mehrere hundert Domains und der Bestand sieht aus, als sei er
     * verschwunden.
     */
    private const SEITENGROESSE = 1000;

    /**
     * Meldungen, nach denen sofort Schluss ist.
     */
    private const SPERRMELDUNGEN = ['TOO_MANY_ATTEMPTS', 'WRONG_USERNAME_OR_PASSWORD'];

    /**
     * Wie lange eine Anmeldung wiederverwendet wird. Die Bruecke hielt ihre
     * Sitzung ebenfalls 15 Minuten; der Anbieter wertet haeufige Anmeldungen
     * als Angriff.
     */
    private const SITZUNGS_TTL_MINUTEN = 15;

    /**
     * @param  array{endpoint?: string, branch?: string, username?: string, password?: string, reseller_id?: string|int|null, reseller_ids?: string, test_domain?: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function provider(): RegistrarProvider
    {
        return RegistrarProvider::ResellerInterface;
    }

    /**
     * Eingerichtet ist der Anschluss, wenn Benutzername und Kennwort liegen.
     *
     * Die ResellerID ist optional: ohne sie liest der Anschluss den
     * Hauptaccount.
     */
    public function isConfigured(): bool
    {
        return filled($this->config['username'] ?? null)
            && filled($this->config['password'] ?? null);
    }

    /**
     * Prueft den Zugang ueber `tld/list`.
     *
     * Der Aufruf liest die TLD-Liste und aendert nichts — er beantwortet
     * genau die Frage, ob Zugangsdaten und ResellerID stimmen.
     */
    public function testConnection(): string
    {
        $this->guardConfigured();

        $antwort = $this->call('tld/list', ['limit' => 1]);

        $konto = filled($this->config['reseller_id'] ?? null)
            ? (string) $this->config['reseller_id']
            : 'Hauptaccount';

        return sprintf(
            'ResellerInterface hat geantwortet: %s (Konto %s). Gelesen wurde nur die TLD-Liste — geändert wurde nichts.',
            $this->text($antwort, 'stateName') ?? $this->text($antwort, 'state') ?? 'ohne Statusangabe',
            $konto,
        );
    }

    /**
     * @return iterable<int, RemoteDomain>
     */
    public function domains(): iterable
    {
        $this->guardConfigured();

        foreach ($this->resellerIds() as $resellerId) {
            $offset = 0;

            do {
                $params = ['limit' => self::SEITENGROESSE, 'offset' => $offset];

                if ($resellerId !== null) {
                    $params['resellerID'] = $resellerId;
                }

                $antwort = $this->call('domain/list', $params);
                $liste = $this->liste($antwort, 'domain/list');

                foreach ($liste as $eintrag) {
                    if (is_array($eintrag)) {
                        yield $this->toDomain($eintrag);
                    }
                }

                // Der Anbieter nennt die Gesamtzahl unter `total`; die
                // Seiten laufen, bis alles gesehen wurde. Eine leere Seite
                // beendet das Blaettern ebenfalls: nennt der Anbieter eine
                // Gesamtzahl, die er nicht ausliefert, liefe die Schleife
                // sonst endlos.
                $gesamt = (int) ($antwort['total'] ?? data_get($antwort, 'data.total', 0));
                $offset += count($liste);
            } while ($liste !== [] && $offset < $gesamt);
        }
    }

    /**
     * Der Zertifikatsbestand ueber `tls/list`.
     *
     * Frueher stand hier eine leere Liste mit der Begruendung, die Anleitung
     * kenne keinen Zertifikatsbestand. Die OpenAPI-Beschreibung des Anbieters
     * kennt ihn: `tls/list` liest ihn mit dem Recht „SSL-Zertifikate einsehen"
     * (api.tls.view), also rein lesend, und blaettert wie `domain/list` ueber
     * `limit` und `offset`.
     *
     * @return iterable<int, RemoteCertificate>
     */
    public function certificates(): iterable
    {
        $this->guardConfigured();

        foreach ($this->resellerIds() as $resellerId) {
            $offset = 0;

            do {
                $params = [
                    'limit' => self::SEITENGROESSE,
                    'offset' => $offset,
                    // Ohne diese Angabe nennt die Liste nur die Kennung des
                    // aktiven Zertifikats. Erst damit liegt das Zertifikat
                    // selbst daneben — und mit ihm `validTill`, das echte Ende
                    // der Gueltigkeit. Ein Abrechnungsdatum waere es nicht.
                    'include' => ['certificates'],
                ];

                if ($resellerId !== null) {
                    $params['resellerID'] = $resellerId;
                }

                $antwort = $this->call('tls/list', $params);
                $gesamt = (int) ($antwort['total'] ?? data_get($antwort, 'data.total', 0));

                /*
                 * Ein Konto ohne Zertifikate ist der Normalfall und keine
                 * kaputte Antwort: nennt der Anbieter die Gesamtzahl 0 und
                 * fuehrt gar keine Liste, ist der Bestand eben leer. Bei
                 * `domain/list` ist das anders — dort waere eine fehlende
                 * Liste ein verschwundener Bestand.
                 */
                if ($gesamt === 0 && ($antwort['list'] ?? null) === null) {
                    break;
                }

                $liste = $this->liste($antwort, 'tls/list');

                foreach ($liste as $eintrag) {
                    if (is_array($eintrag)) {
                        yield $this->toCertificate($eintrag);
                    }
                }

                $offset += count($liste);
            } while ($liste !== [] && $offset < $gesamt);
        }
    }

    /**
     * Dieser Anschluss kann eine Zone lesen.
     *
     * Lange stand hier `false`: die Kategorie `dns/*` war bekannt, aber von
     * ihren Funktionen waren nur *schreibende* belegt (Zone anlegen, aendern,
     * Eintraege setzen, loeschen). Der lesende Name wurde nicht erraten, weil
     * ein Fehlversuch bei genau diesem Anbieter schon einmal das Konto
     * gesperrt hat.
     *
     * Die OpenAPI-Beschreibung nennt ihn: `dns/getZoneDetails` („Details zu
     * einer Zone anzeigen", Alias `dns/listRecords`) mit dem Recht „Zonen
     * einsehen" (api.dns.view). Damit steht der Name fest und ist nicht
     * geraten.
     */
    public function canReadZone(): bool
    {
        return true;
    }

    /**
     * Liest eine Zone ueber `dns/getZoneDetails`.
     *
     * Pflichtparameter ist allein der Domainname; die Antwort haelt die SOA
     * unter `soa`, die Eintraege unter `records` und den virtuellen
     * Nameserver-Satz unter `vns`. Der Aufruf liest und aendert nichts — das
     * Recht dazu heisst „Zonen einsehen".
     */
    public function zone(string $domain): DnsZone
    {
        $this->guardConfigured();

        $name = mb_strtolower(trim($domain));

        if ($name === '') {
            throw new RegistrarException('Ohne Domainnamen lässt sich keine Zone lesen.');
        }

        $antwort = $this->call('dns/getZoneDetails', ['domain' => $name]);

        $soa = is_array($antwort['soa'] ?? null) ? $antwort['soa'] : [];
        $records = $this->records($antwort, $name);

        return new DnsZone(
            origin: mb_strtolower($this->text($antwort, 'domain') ?? $name),
            nameservers: $this->nameserver($antwort, $records),
            records: $records,
            ttl: $this->zahl($soa, 'ttl'),
            soaEmail: $this->text($soa, 'mail'),
            updatedAt: $this->seriennummer($soa),
        );
    }

    /**
     * Die Eintraege der Zone.
     *
     * Anders als bei anderen Anbietern ist `records` keine Liste, sondern eine
     * Zuordnung `Record-ID => Eintrag`. Nur die Werte sind gemeint; die
     * Schluessel wiederholen die `id` daneben.
     *
     * Fehlt `records` ganz, bricht der Aufruf ab: eine leere Zone anzuzeigen,
     * wo der Anbieter etwas anderes gemeint hat, waere schlimmer als ein
     * Fehler — jemand koennte daraus schliessen, ein Eintrag sei verschwunden.
     *
     * @param  array<string, mixed>  $antwort
     * @return array<int, DnsRecord>
     */
    private function records(array $antwort, string $zone): array
    {
        $eintraege = $antwort['records'] ?? null;

        if (! is_array($eintraege)) {
            throw new RegistrarException(
                "ResellerInterface hat zu {$zone} keine Eintragsliste geliefert (records fehlt).",
                $antwort,
            );
        }

        $records = [];

        foreach ($eintraege as $eintrag) {
            if (! is_array($eintrag)) {
                continue;
            }

            $typ = $this->text($eintrag, 'type');

            if ($typ === null) {
                continue;
            }

            $typ = mb_strtoupper($typ);
            $inhalt = $this->text($eintrag, 'content') ?? $this->sonderdaten($eintrag);

            if ($inhalt === null) {
                continue;
            }

            $records[] = new DnsRecord(
                name: $this->recordName($eintrag, $zone),
                type: $typ,
                content: $inhalt,
                ttl: $this->zahl($eintrag, 'ttl'),
                // Der Anbieter fuehrt `priority` bei jedem Eintrag und setzt
                // sie sonst auf 0. Uebernommen wird sie nur, wo sie eine
                // Bedeutung hat — bei MX und SRV ist auch die 0 eine Angabe.
                priority: in_array($typ, ['MX', 'SRV'], strict: true)
                    ? $this->zahl($eintrag, 'priority')
                    : null,
            );
        }

        return $records;
    }

    /**
     * Der Name eines Eintrags, relativ zur Zone.
     *
     * Der Anbieter schreibt den Ursprung als vollen Domainnamen
     * (`john-doe.de`) und Unternamen verkuerzt (`www`). Das Portal zeigt den
     * Ursprung wie ueberall als `@`; ein voll ausgeschriebener Untername wird
     * auf denselben Stand gebracht, damit zwei Anbieter dieselbe Zone gleich
     * darstellen.
     *
     * @param  array<string, mixed>  $eintrag
     */
    private function recordName(array $eintrag, string $zone): string
    {
        $name = $this->text($eintrag, 'name');

        if ($name === null) {
            return '@';
        }

        $name = mb_strtolower(rtrim($name, '.'));

        if ($name === '' || $name === $zone) {
            return '@';
        }

        if (str_ends_with($name, '.'.$zone)) {
            return mb_substr($name, 0, -mb_strlen('.'.$zone));
        }

        return $name;
    }

    /**
     * Die Angaben eines Spezial-Records.
     *
     * Eintraege wie `HEADER` (eine Weiterleitung) tragen kein `content`,
     * sondern ihre Angaben unter `data` — die Beschreibung nennt das Feld
     * „Enthaelt weitere Daten fuer Spezial-Records", ohne festen Satz an
     * Schluesseln. Sie werden als `feld=wert` aneinandergereiht: das gibt
     * wieder, was dort steht, ohne eine Bedeutung zu erfinden. Sie ganz zu
     * uebergehen waere schlechter — eine Weiterleitung fehlte dann genau dort,
     * wo man sie sucht.
     *
     * @param  array<string, mixed>  $eintrag
     */
    private function sonderdaten(array $eintrag): ?string
    {
        $daten = $eintrag['data'] ?? null;

        if (! is_array($daten)) {
            return null;
        }

        $teile = [];

        foreach ($daten as $feld => $wert) {
            if (! is_scalar($wert) || (string) $wert === '') {
                continue;
            }

            $teile[] = is_string($feld) ? "{$feld}={$wert}" : (string) $wert;
        }

        return $teile === [] ? null : implode(' ', $teile);
    }

    /**
     * Die Nameserver der Zone.
     *
     * Der Anbieter nennt sie an zwei Stellen: unter `vns.hostname` steht der
     * virtuelle Nameserver-Satz, mit dem er die Zone fuehrt, und in der Zone
     * selbst liegen NS-Eintraege auf dem Ursprung. Vorrang hat `vns` — das ist
     * die Zuordnung des Anbieters, die NS-Eintraege sind ihr Abbild.
     *
     * @param  array<string, mixed>  $antwort
     * @param  array<int, DnsRecord>  $records
     * @return array<int, string>
     */
    private function nameserver(array $antwort, array $records): array
    {
        $vns = $antwort['vns'] ?? null;

        $namen = is_array($vns) ? $this->namen($vns, 'hostname') : [];

        if ($namen !== []) {
            return $namen;
        }

        $ausRecords = [];

        foreach ($records as $record) {
            if ($record->type === 'NS' && $record->name === '@') {
                $ausRecords[] = mb_strtolower(rtrim($record->content, '.'));
            }
        }

        return array_values(array_unique($ausRecords));
    }

    /**
     * Wann die Zone zuletzt geaendert wurde — aus der Seriennummer der SOA.
     *
     * Ein eigenes Aenderungsdatum liefert der Anbieter nicht. Seine
     * Seriennummer folgt dem ueblichen Muster JJJJMMTT plus Zaehler (das
     * Beispiel der Beschreibung lautet `2026092886`). Gelesen werden nur die
     * ersten acht Ziffern, und nur wenn sie ein gueltiges Datum ergeben: eine
     * Zone mit fortlaufend gezaehlter Seriennummer liefert dann eben kein
     * Datum statt eines falschen.
     *
     * @param  array<string, mixed>  $soa
     */
    private function seriennummer(array $soa): ?CarbonImmutable
    {
        $serial = $this->text($soa, 'serial');

        if ($serial === null || preg_match('/^(\d{8})\d{0,2}$/', $serial, $treffer) !== 1) {
            return null;
        }

        $jahr = (int) mb_substr($treffer[1], 0, 4);
        $monat = (int) mb_substr($treffer[1], 4, 2);
        $tag = (int) mb_substr($treffer[1], 6, 2);

        if (! checkdate($monat, $tag, $jahr)) {
            return null;
        }

        return CarbonImmutable::create($jahr, $monat, $tag) ?: null;
    }

    /**
     * Ein Zertifikat aus `tls/list`.
     *
     * Der Eintrag beschreibt den Vertrag (`tlsID`, Status, Abrechnung), das
     * Zertifikat selbst liegt darin unter `activeCertificate` mit `san`,
     * `issuedAt`, `validFrom` und `validTill`.
     *
     * @param  array<string, mixed>  $eintrag
     */
    private function toCertificate(array $eintrag): RemoteCertificate
    {
        $domains = $this->namen($eintrag, 'domains');

        $zertifikat = is_array($eintrag['activeCertificate'] ?? null)
            ? $eintrag['activeCertificate']
            : [];

        $san = $this->namen($zertifikat, 'san');

        $name = $domains[0] ?? $san[0] ?? null;

        if ($name === null) {
            throw new RegistrarException(
                'ResellerInterface hat einen Zertifikatseintrag ohne Domain geliefert.',
                $eintrag,
            );
        }

        return new RemoteCertificate(
            commonName: $name,
            reference: $this->text($eintrag, 'tlsID'),
            status: implode(' ', array_filter([
                $this->text($eintrag, 'state'),
                $this->text($eintrag, 'subState'),
            ])) ?: 'unknown',
            // Keinen Aussteller: die Liste nennt nur eine Produkt-ID
            // (`tlsProductID`), und `type` heisst dort etwa „legacy". Beides
            // ist keine ausstellende Stelle, und erfunden wird hier keine.
            issuer: null,
            issuedOn: $this->zeitstempel($zertifikat, 'issuedAt')
                ?? $this->zeitstempel($zertifikat, 'validFrom')
                ?? $this->zeitstempel($eintrag, 'createDate'),
            // `validTill` ist das echte Ende der Gueltigkeit. Fehlt das aktive
            // Zertifikat — etwa solange eine Bestellung laeuft —, bleibt nur
            // die naechste Abrechnung als Naehrung; sie ist als solche zu
            // lesen und nicht als Ablauf des Zertifikats.
            expiresOn: $this->zeitstempel($zertifikat, 'validTill')
                ?? $this->zeitstempel($eintrag, 'nextBillingDate'),
            alternativeNames: array_values(array_diff($san !== [] ? $san : $domains, [$name])),
        );
    }

    /**
     * Eine Liste von Namen aus der Antwort, kleingeschrieben und ohne Punkt
     * am Ende.
     *
     * @param  array<string, mixed>  $eintrag
     * @return array<int, string>
     */
    private function namen(array $eintrag, string $feld): array
    {
        $werte = $eintrag[$feld] ?? null;

        if (! is_array($werte)) {
            return [];
        }

        $namen = [];

        foreach ($werte as $wert) {
            if (! is_scalar($wert) || (string) $wert === '') {
                continue;
            }

            $namen[] = mb_strtolower(rtrim((string) $wert, '.'));
        }

        return array_values(array_unique($namen));
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function zahl(array $eintrag, string $feld): ?int
    {
        $wert = $eintrag[$feld] ?? null;

        return is_numeric($wert) ? (int) $wert : null;
    }

    /**
     * Die Konten, deren Bestand gelesen wird.
     *
     * Die eingetragene `reseller_id` aus den Zugangsdaten steht fuer den
     * Hauptaccount; ohne sie liefert `domain/list` ebenfalls genau ihn.
     * Weitere Konten kommen als Liste dazu, etwa "59163" fuer den
     * Subreseller.
     *
     * @return array<int, string|null>
     */
    private function resellerIds(): array
    {
        $haupt = filled($this->config['reseller_id'] ?? null)
            ? (string) $this->config['reseller_id']
            : null;

        $weitere = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($this->config['reseller_ids'] ?? '')),
        ), fn (string $id): bool => $id !== '' && $id !== $haupt));

        return [$haupt, ...$weitere];
    }

    /**
     * Ein einzelner Aufruf des Anbieters. Ohne Wiederholung, mit Absicht.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $aktion, array $params = []): array
    {
        if (! in_array($aktion, self::ERLAUBTE_AKTIONEN, strict: true)) {
            throw new RegistrarException(
                "Die Aktion {$aktion} ist für diesen Anschluss nicht vorgesehen. Er liest nur.",
            );
        }

        try {
            $antwort = $this->request()->post($aktion, $params);
        } catch (Throwable $fehler) {
            throw new RegistrarException(
                "Die Verbindung zu ResellerInterface ist fehlgeschlagen: {$fehler->getMessage()}",
            );
        }

        return $this->guardAntwort($aktion, $antwort, $params);
    }

    /**
     * Der HTTP-Client mit der laufenden Sitzung als Cookie `coreSID`.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl($this->endpoint())
            ->timeout(120)
            ->connectTimeout(15)
            ->acceptJson()
            ->asForm()
            ->withCookies(['coreSID' => $this->sitzung() ?? ''], $this->host());
    }

    /**
     * Der Endpunkt inklusive Branch (`stable`).
     */
    private function endpoint(): string
    {
        return rtrim((string) ($this->config['endpoint'] ?? 'https://core.resellerinterface.de'), '/').'/';
    }

    private function host(): string
    {
        return (string) parse_url($this->endpoint(), PHP_URL_HOST);
    }

    /**
     * Die laufende Anmeldung, aus dem Cache oder durch einen einzigen Login.
     */
    private function sitzung(): ?string
    {
        $vorhanden = Cache::get($this->cacheSchluessel());

        if (is_string($vorhanden) && $vorhanden !== '') {
            return $vorhanden;
        }

        $benutzer = (string) ($this->config['username'] ?? '');
        $kennwort = (string) ($this->config['password'] ?? '');

        if ($benutzer === '' || $kennwort === '') {
            throw new RegistrarException(
                'Für ResellerInterface fehlen Benutzername oder Kennwort. Ohne sie ist kein Zugriff möglich.',
            );
        }

        $felder = [
            'username' => $benutzer,
            'password' => $kennwort,
        ];

        if (filled($this->config['reseller_id'] ?? null)) {
            $felder['resellerId'] = (string) $this->config['reseller_id'];
        }

        try {
            $antwort = Http::baseUrl($this->endpoint())
                ->timeout(60)
                ->connectTimeout(15)
                ->acceptJson()
                ->asForm()
                ->post('reseller/login', $felder);
        } catch (Throwable $fehler) {
            throw new RegistrarException(
                "Die Anmeldung bei ResellerInterface ist fehlgeschlagen: {$fehler->getMessage()}",
            );
        }

        $inhalt = $antwort->json();

        // Der Umschlag des Anbieters kennt kein `success`-Feld: Erfolg ist
        // ein `state` unter 2000 (1000 OK, 1001 OK_PENDING, 1003 NO_CHANGE),
        // alles ab 2000 ein Fehler.
        if (! is_array($inhalt) || ! $this->istErfolg($inhalt)) {
            $meldung = is_array($inhalt)
                ? (string) ($inhalt['stateName'] ?? $inhalt['state'] ?? (json_encode($inhalt) ?: 'ohne Meldung'))
                : 'ohne Meldung';

            throw $this->fehler('reseller/login', $meldung, $inhalt);
        }

        // Die Sitzung kehrt als Cookie `coreSID` zurück — im Roh-Kopf, denn
        // die Http-Client-Antwort stellt Cookies nicht als Feld bereit.
        $sitzung = $this->cookieAusKopf($antwort, 'coreSID');

        if ($sitzung === null) {
            throw new RegistrarException(
                'ResellerInterface hat nach der Anmeldung keine Sitzungskennung (coreSID) geliefert.',
                $inhalt,
            );
        }

        Cache::put($this->cacheSchluessel(), $sitzung, now()->addMinutes(self::SITZUNGS_TTL_MINUTEN));

        return $sitzung;
    }

    /**
     * Cache-Schluessel je Konto: zwei Konten teilen sich keine Sitzung.
     */
    private function cacheSchluessel(): string
    {
        $konto = filled($this->config['reseller_id'] ?? null)
            ? (string) $this->config['reseller_id']
            : 'haupt';

        return "registrar.resellerinterface.session.{$konto}";
    }

    /**
     * Den Wert eines Cookies aus dem Set-Cookie-Kopf der Antwort lesen.
     *
     * Mehrere Kopfzeilen stehen unter demselben Schlüssel; gesucht wird der
     * mit dem gesuchten Namen, dessen Wert bis zum ersten Semikpon reicht.
     */
    private function cookieAusKopf(Response $antwort, string $name): ?string
    {
        $kopfzeilen = $antwort->getHeader('Set-Cookie');

        foreach ($kopfzeilen as $zeile) {
            if (preg_match('/'.$name.'=([^;]+)/', $zeile, $treffer)) {
                return trim($treffer[1]);
            }
        }

        return null;
    }

    /**
     * Prueft den Umschlag der Antwort.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function guardAntwort(string $aktion, Response $antwort, array $params): array
    {
        $inhalt = $antwort->json();

        if (! is_array($inhalt)) {
            throw new RegistrarException(
                "ResellerInterface hat auf {$aktion} keine verwertbare Antwort geliefert (HTTP {$antwort->status()}).",
                mb_substr((string) $antwort->body(), 0, 500),
            );
        }

        if ($this->istErfolg($inhalt)) {
            return $inhalt;
        }

        $meldung = $this->text($inhalt, 'stateName')
            ?? $this->text($inhalt, 'state')
            ?? $this->text($inhalt, 'message')
            ?? '';

        if ($meldung === '') {
            $meldung = json_encode($inhalt) ?: '';
        }

        // Eine abgelaufene oder unbekannte Sitzung ist der eine Fall, in dem
        // ein zweiter Anlauf erlaubt ist: genau einmal die Anmeldung erneuern,
        // dann den Aufruf wiederholen. Alles andere wird nie wiederholt.
        if ($this->istSitzungsfehler($inhalt)) {
            Cache::forget($this->cacheSchluessel());

            $antwort = $this->request()->post($aktion, $params);

            return $this->pruefeErneut($aktion, $antwort);
        }

        throw $this->fehler($aktion, $meldung, $inhalt);
    }

    /**
     * Die Antwort nach der Neuanmeldung — diesmal ohne zweiten Anlauf.
     *
     * @return array<string, mixed>
     */
    private function pruefeErneut(string $aktion, Response $antwort): array
    {
        $inhalt = $antwort->json();

        if (! is_array($inhalt) || ! $this->istErfolg($inhalt)) {
            $meldung = is_array($inhalt)
                ? (string) ($inhalt['stateName'] ?? $inhalt['state'] ?? (json_encode($inhalt) ?: 'ohne Meldung'))
                : "HTTP {$antwort->status()}";

            throw $this->fehler($aktion, $meldung, $inhalt);
        }

        return $inhalt;
    }

    /**
     * Der Erfolgsmassstab des Anbieters: `state` unter 2000 — oder das Feld
     * `success`, das aeltere Antworten tragen. Beides zu akzeptieren kostet
     * nichts und bricht bei anders geformten Umschlaegen nicht.
     *
     * @param  array<string, mixed>  $inhalt
     */
    private function istErfolg(array $inhalt): bool
    {
        if (($inhalt['success'] ?? null) === true) {
            return true;
        }

        if (($inhalt['success'] ?? null) === false) {
            return false;
        }

        $state = $inhalt['state'] ?? null;

        if (is_int($state) || (is_string($state) && ctype_digit($state))) {
            return (int) $state < 2000;
        }

        return false;
    }

    /**
     * Erkennt eine abgelaufene oder unbekannte Sitzung.
     *
     * @param  array<string, mixed>  $inhalt
     */
    private function istSitzungsfehler(array $inhalt): bool
    {
        $meldung = strtoupper(implode(' ', array_filter([
            (string) ($inhalt['stateName'] ?? ''),
            (string) ($inhalt['state'] ?? ''),
            (string) ($inhalt['message'] ?? ''),
        ])));

        return str_contains($meldung, 'SESSION')
            || str_contains($meldung, 'AUTH')
            || str_contains($meldung, 'LOGIN')
            || str_contains($meldung, 'SID');
    }

    /**
     * Baut die Ausnahme — und macht eine Kontosperre unuebersehbar.
     */
    private function fehler(string $aktion, string $meldung, mixed $rohantwort = null): RegistrarException
    {
        foreach (self::SPERRMELDUNGEN as $sperre) {
            if (str_contains($meldung, $sperre)) {
                return new RegistrarException(
                    sprintf(
                        'ResellerInterface meldet %s. Das Konto ist gesperrt oder kurz davor: jeder weitere Versuch verlängert die Sperre. '
                        .'Der Import bricht hier ab und wiederholt nichts — bitte von Hand klären, bevor er erneut läuft.',
                        $sperre,
                    ),
                    $rohantwort,
                );
            }
        }

        return new RegistrarException(
            sprintf('ResellerInterface meldet zu %s: %s', $aktion, $meldung !== '' ? $meldung : 'ohne Meldung'),
            $rohantwort,
        );
    }

    /**
     * Die Liste aus der Antwort.
     *
     * `domain/list` und `tls/list` legen sie direkt unter `list` ab (neben
     * `state` und `total`), ohne `data`-Umschlag — anders als die Preise, deren
     * Liste unter `data.list` liegt. Beide Wege werden gelesen, abgebrochen
     * wird, wenn keiner etwas haelt: stillschweigend nichts einzulesen waere
     * schlimmer als der Abbruch.
     *
     * @param  array<string, mixed>  $antwort
     * @return array<int, mixed>
     */
    private function liste(array $antwort, string $aktion): array
    {
        $liste = $antwort['list'] ?? data_get($antwort, 'data.list');

        if (! is_array($liste)) {
            throw new RegistrarException(
                "Die Antwort auf {$aktion} enthält keine Liste (weder unter list noch data.list).",
                $antwort,
            );
        }

        return array_values($liste);
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function toDomain(array $eintrag): RemoteDomain
    {
        $name = $this->text($eintrag, 'domain') ?? $this->text($eintrag, 'domainAce');

        if ($name === null) {
            throw new RegistrarException(
                'ResellerInterface hat einen Domaineintrag ohne Namen geliefert.',
                $eintrag,
            );
        }

        // Ein echtes Ablaufdatum liefert die Liste nicht; `latestCancellationDate`
        // ist die naechste Verlaengerungsgrenze und damit die beste Naehrung.
        // Alle Zeiten kommen als Unix-Zeitstempel — als Ziffernfolge.
        $laeuftAus = $this->zeitstempel($eintrag, 'latestCancellationDate')
            ?? $this->zeitstempel($eintrag, 'deleteDate');

        return new RemoteDomain(
            name: mb_strtolower($name),
            reference: $this->text($eintrag, 'domainID'),
            // `state` nennt den Zustand; `subState` haelt Sonderfaele wie
            // PENDING oder REVOKED daneben und wird mitgefuehrt, wenn steht.
            status: implode(' ', array_filter([
                $this->text($eintrag, 'state'),
                $this->text($eintrag, 'subState'),
            ])) ?: 'unknown',
            registeredOn: $this->zeitstempel($eintrag, 'createDate') ?? $this->zeitstempel($eintrag, 'orderDate'),
            expiresOn: $laeuftAus,
            // Ungekuendigt und ohne Loeschmodus heisst: laeuft automatisch
            // weiter — eine Naehrung, das Feld selbst kennt der Anbieter in
            // der Liste nicht.
            autoRenew: ($eintrag['cancellationDate'] ?? null) === null
                && blank($this->text($eintrag, 'deleteMode')),
            // `domain/list` nennt Nameserver nur, wenn der Aufruf sie mit
            // `include[] = nameserver` anfordert. Der Import tut das nicht:
            // wer sie sehen will, schlaegt die Zone auf, und die kommt
            // vollstaendig aus `dns/getZoneDetails`.
            nameservers: [],
        );
    }

    /**
     * Unix-Zeitstempel aus der Antwort — als Zahl oder Ziffernfolge.
     *
     * @param  array<string, mixed>  $eintrag
     */
    private function zeitstempel(array $eintrag, string $feld): ?CarbonImmutable
    {
        $wert = $eintrag[$feld] ?? null;

        if ($wert === null || $wert === '' || $wert === '0' || $wert === 0) {
            return null;
        }

        if (is_numeric($wert)) {
            return CarbonImmutable::createFromTimestamp((int) $wert);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function text(array $eintrag, string $feld): ?string
    {
        $wert = $eintrag[$feld] ?? null;

        return is_scalar($wert) && (string) $wert !== '' ? (string) $wert : null;
    }

    private function guardConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RegistrarException(
                'Für ResellerInterface fehlen Benutzername oder Kennwort. Ohne sie ist kein Zugriff möglich — '
                .'sie werden unter „Schnittstellen" hinterlegt.',
            );
        }
    }
}
