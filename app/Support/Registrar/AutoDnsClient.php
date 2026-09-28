<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Anschluss an die JSON-Schnittstelle von autoDNS (InterNetX Domainrobot).
 *
 * Die Feldnamen stammen aus der offiziellen OpenAPI-Beschreibung des
 * Anbieters (InterNetX/domainrobot-api, `src/domainrobot.json`) und sind
 * nicht geraten.
 *
 * Angemeldet wird mit Basic-Auth und dem Kontext im Kopf
 * `X-Domainrobot-Context` — 1 ist das Testsystem, 4 oder die eigene
 * Kontextnummer das Livesystem.
 *
 * Jede Antwort traegt denselben Umschlag:
 *
 *     { "stid": "…", "status": { "code": "S0301", "type": "SUCCESS" },
 *       "object": { … }, "data": [ … ] }
 *
 * Nur lesend: `/hello`, `/domain/_search`, `/certificate/_search` und
 * `/zone/{name}`.
 */
class AutoDnsClient implements RegistrarClient
{
    /**
     * Wie viele Eintraege je Abfrage. Die Schnittstelle blaettert ueber
     * `view.offset`.
     */
    private const SEITENGROESSE = 100;

    /**
     * @param  array{endpoint?: string, username?: string, password?: string, context?: string, name_server?: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function provider(): RegistrarProvider
    {
        return RegistrarProvider::AutoDns;
    }

    public function isConfigured(): bool
    {
        return filled($this->config['username'] ?? null)
            && filled($this->config['password'] ?? null)
            && filled($this->config['context'] ?? null);
    }

    /**
     * Prueft den Zugang ueber `/hello`.
     *
     * Der Endpunkt liest keine Daten und aendert nichts — er beantwortet
     * genau die Frage, ob Zugangsdaten und Kontext stimmen.
     */
    public function testConnection(): string
    {
        $this->guardConfigured();

        $antwort = $this->send('get', 'hello');
        $umschlag = $this->envelope($antwort, 'hello');

        return sprintf(
            'autoDNS hat geantwortet: %s (%s), Kontext %s.',
            $umschlag['status']['text'] ?? 'ohne Meldung',
            $umschlag['status']['code'] ?? 'ohne Code',
            $this->config['context'] ?? '?',
        );
    }

    /**
     * @return iterable<int, RemoteDomain>
     */
    public function domains(): iterable
    {
        foreach ($this->search('domain/_search') as $eintrag) {
            $name = $this->text($eintrag, 'name') ?? $this->text($eintrag, 'idn');

            if ($name === null) {
                throw new RegistrarException(
                    'autoDNS hat einen Domaineintrag ohne Namen geliefert.',
                    $eintrag,
                );
            }

            yield new RemoteDomain(
                name: mb_strtolower($name),
                // autoDNS vergibt keine eigene Kennung je Domain; der Name ist
                // dort der Schluessel.
                reference: null,
                status: $this->text($eintrag, 'registryStatus') ?? 'unknown',
                // `domainCreated` ist das Datum bei der Registrierungsstelle,
                // `created` nur das Anlegen im Portal des Anbieters.
                registeredOn: $this->date($eintrag, 'domainCreated') ?? $this->date($eintrag, 'created'),
                expiresOn: $this->date($eintrag, 'expire'),
                // TRUE, FALSE oder ONCE — nur TRUE verlaengert dauerhaft.
                autoRenew: $this->text($eintrag, 'autoRenewStatus') === 'TRUE',
                nameservers: $this->names($eintrag, 'nameServers'),
            );
        }
    }

    /**
     * @return iterable<int, RemoteCertificate>
     */
    public function certificates(): iterable
    {
        foreach ($this->search('certificate/_search') as $eintrag) {
            $name = $this->text($eintrag, 'name') ?? $this->text($eintrag, 'idn');

            if ($name === null) {
                throw new RegistrarException(
                    'autoDNS hat einen Zertifikatseintrag ohne Namen geliefert.',
                    $eintrag,
                );
            }

            yield new RemoteCertificate(
                commonName: mb_strtolower($name),
                reference: $this->text($eintrag, 'id'),
                // Das Zertifikatsobjekt von autoDNS trägt keinen einfachen
                // Status; erfunden wird hier keiner.
                status: 'unknown',
                issuer: $this->text($eintrag, 'product'),
                issuedOn: $this->date($eintrag, 'created'),
                expiresOn: $this->date($eintrag, 'expire'),
                alternativeNames: $this->names($eintrag, 'subjectAlternativeNames'),
            );
        }
    }

    public function canReadZone(): bool
    {
        return true;
    }

    /**
     * Liest eine Zone ueber `GET zone/{name}` — „Zone Info" (0205).
     *
     * Die Schnittstelle kennt denselben Aufruf auch mit dem verwaltenden
     * Nameserver im Pfad (`zone/{name}/{virtualNameServer}`). Manche Konten
     * brauchen ihn, weil dieselbe Zone auf mehreren Nameservern liegen kann;
     * er steht darum als Einstellung bereit und wird nur mitgeschickt, wenn er
     * gesetzt ist. Geraten wird er nicht.
     */
    public function zone(string $domain): DnsZone
    {
        $this->guardConfigured();

        $name = mb_strtolower(trim($domain));

        if ($name === '') {
            throw new RegistrarException('Ohne Domainnamen lässt sich keine Zone lesen.');
        }

        $nameserver = $this->text($this->config, 'name_server');

        $pfad = $nameserver === null
            ? 'zone/'.rawurlencode($name)
            : 'zone/'.rawurlencode($name).'/'.rawurlencode($nameserver);

        $umschlag = $this->envelope($this->send('get', $pfad), $pfad);

        $zone = $umschlag['data'][0] ?? null;

        if (! is_array($zone)) {
            throw new RegistrarException(
                "autoDNS hat zu {$name} keine Zone geliefert.",
                $umschlag,
            );
        }

        return new DnsZone(
            origin: $this->text($zone, 'origin') ?? $name,
            nameservers: $this->names($zone, 'nameServers'),
            records: $this->records($zone),
            ttl: $this->number($zone['soa'] ?? [], 'ttl'),
            soaEmail: is_array($zone['soa'] ?? null) ? $this->text($zone['soa'], 'email') : null,
            updatedAt: $this->date($zone, 'updated'),
        );
    }

    /**
     * Die Eintraege einer Zone.
     *
     * Neben `resourceRecords` fuehrt autoDNS die Haupt-IP getrennt: aus ihr
     * entsteht der A-Eintrag auf dem Ursprung, und bei `wwwInclude` zusaetzlich
     * der auf `www`. Beide stehen in keiner Eintragsliste, gelten im DNS aber
     * trotzdem — sie fehlten hier sonst genau dort, wo man sie zuerst sucht.
     * Sie sind als abgeleitet gekennzeichnet, damit die Anzeige sie nicht als
     * echte Zeilen der Zone ausgibt.
     *
     * @param  array<string, mixed>  $zone
     * @return array<int, DnsRecord>
     */
    private function records(array $zone): array
    {
        $records = [];

        $eintraege = $zone['resourceRecords'] ?? [];

        foreach (is_array($eintraege) ? $eintraege : [] as $eintrag) {
            if (! is_array($eintrag)) {
                continue;
            }

            $typ = $this->text($eintrag, 'type');
            $wert = $this->text($eintrag, 'value');

            if ($typ === null || $wert === null) {
                continue;
            }

            $records[] = new DnsRecord(
                name: $this->text($eintrag, 'name') ?? '@',
                type: mb_strtoupper($typ),
                content: $wert,
                ttl: $this->number($eintrag, 'ttl'),
                priority: $this->number($eintrag, 'pref'),
            );
        }

        $haupt = $zone['main'] ?? null;
        $adresse = is_array($haupt) ? $this->text($haupt, 'address') : null;

        if ($adresse !== null) {
            $ttl = is_array($haupt) ? $this->number($haupt, 'ttl') : null;
            $typ = str_contains($adresse, ':') ? 'AAAA' : 'A';

            $records[] = new DnsRecord('@', $typ, $adresse, $ttl, derived: true);

            if (($zone['wwwInclude'] ?? false) === true) {
                $records[] = new DnsRecord('www', $typ, $adresse, $ttl, derived: true);
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function number(array $eintrag, string $feld): ?int
    {
        $wert = $eintrag[$feld] ?? null;

        return is_numeric($wert) ? (int) $wert : null;
    }

    /**
     * Blaettert durch eine Suche, bis eine Seite nicht mehr voll ist.
     *
     * @return iterable<int, array<string, mixed>>
     */
    private function search(string $pfad): iterable
    {
        $this->guardConfigured();

        $offset = 0;

        do {
            $antwort = $this->send('post', $pfad, [
                'view' => ['limit' => self::SEITENGROESSE, 'offset' => $offset],
            ]);

            $eintraege = $this->envelope($antwort, $pfad)['data'] ?? [];

            if (! is_array($eintraege)) {
                throw new RegistrarException(
                    "autoDNS hat auf {$pfad} keine Liste geliefert.",
                    $antwort->json(),
                );
            }

            foreach ($eintraege as $eintrag) {
                if (is_array($eintrag)) {
                    yield $eintrag;
                }
            }

            $offset += count($eintraege);
        } while (count($eintraege) === self::SEITENGROESSE);
    }

    /**
     * @param  'get'|'post'  $verb
     * @param  array<string, mixed>|null  $koerper
     */
    private function send(string $verb, string $pfad, ?array $koerper = null): Response
    {
        try {
            return match ($verb) {
                'get' => $this->request()->get($pfad),
                'post' => $this->request()->post($pfad, $koerper ?? []),
            };
        } catch (Throwable $fehler) {
            throw new RegistrarException(
                "Die Verbindung zu autoDNS ist fehlgeschlagen: {$fehler->getMessage()}",
            );
        }
    }

    /**
     * Prueft den Umschlag und gibt ihn zurueck.
     *
     * @return array<string, mixed>
     */
    private function envelope(Response $antwort, string $pfad): array
    {
        $inhalt = $antwort->json();

        if (! is_array($inhalt) || ! isset($inhalt['status'])) {
            throw new RegistrarException(
                "autoDNS hat auf {$pfad} keine verwertbare Antwort geliefert (HTTP {$antwort->status()}).",
                mb_substr((string) $antwort->body(), 0, 500),
            );
        }

        if (($inhalt['status']['type'] ?? '') !== 'SUCCESS') {
            throw new RegistrarException(
                sprintf(
                    'autoDNS meldet zu %s: %s (%s)',
                    $pfad,
                    $inhalt['status']['text'] ?? $this->firstMessage($inhalt) ?? 'ohne Meldung',
                    $inhalt['status']['code'] ?? 'ohne Code',
                ),
                $inhalt,
            );
        }

        return $inhalt;
    }

    /**
     * @param  array<string, mixed>  $inhalt
     */
    private function firstMessage(array $inhalt): ?string
    {
        $meldungen = $inhalt['messages'] ?? [];

        if (is_array($meldungen) && isset($meldungen[0]['text']) && is_string($meldungen[0]['text'])) {
            return $meldungen[0]['text'];
        }

        return null;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->config['endpoint'] ?? 'https://api.autodns.com/v1/')
            ->timeout(30)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($this->config['username'] ?? '', $this->config['password'] ?? '')
            ->withHeaders(['X-Domainrobot-Context' => (string) ($this->config['context'] ?? '')]);
    }

    private function guardConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RegistrarException(
                'Für autoDNS fehlen Zugangsdaten oder der Kontext. Ohne sie ist kein Zugriff möglich.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function text(array $eintrag, string $feld): ?string
    {
        $wert = $eintrag[$feld] ?? null;

        return is_scalar($wert) && (string) $wert !== '' ? (string) $wert : null;
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function date(array $eintrag, string $feld): ?CarbonImmutable
    {
        $wert = $this->text($eintrag, $feld);

        if ($wert === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($wert);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Die `name`-Werte aus einer Liste von Objekten — Nameserver und
     * alternative Namen liefert autoDNS beide in dieser Form.
     *
     * @param  array<string, mixed>  $eintrag
     * @return array<int, string>
     */
    private function names(array $eintrag, string $feld): array
    {
        $liste = $eintrag[$feld] ?? [];

        if (! is_array($liste)) {
            return [];
        }

        $namen = [];

        foreach ($liste as $element) {
            if (is_array($element) && isset($element['name']) && is_scalar($element['name'])) {
                $namen[] = (string) $element['name'];

                continue;
            }

            if (is_scalar($element) && (string) $element !== '') {
                $namen[] = (string) $element;
            }
        }

        return $namen;
    }
}
