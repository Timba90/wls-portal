<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Anschluss an die Schnittstelle von Domain-Reselling.
 *
 * Die Schnittstelle ist ein schlichter HTTP-Aufruf mit Zugangsdaten und einem
 * Kommando; die Antwort ist kein JSON, sondern eine Liste von Zeilen der Form
 *
 *     [RESPONSE]
 *     code = 200
 *     description = Command completed successfully
 *     property[domain][0] = beispiel.de
 *     property[expirationdate][0] = 2027-04-01 00:00:00
 *     EOF
 *
 * `parseResponse()` wandelt das in ein Array um, `rows()` legt die
 * eigenschaftsweisen Spalten wieder zu Zeilen zusammen.
 *
 * Der Aufbau ist gegen die Dokumentation geschrieben und noch nicht gegen ein
 * echtes Konto geprueft. Deshalb bricht jeder Aufruf mit dem Antwortcode und
 * der Meldung des Anbieters ab, sobald etwas anderes als 200 zurueckkommt —
 * ein unbekanntes Kommando faellt damit sofort auf, statt still nichts zu
 * importieren.
 */
class DomainResellingClient implements RegistrarClient
{
    /**
     * @param  array{endpoint?: string, username?: string, password?: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function provider(): RegistrarProvider
    {
        return RegistrarProvider::DomainReselling;
    }

    public function isConfigured(): bool
    {
        return filled($this->config['username'] ?? null)
            && filled($this->config['password'] ?? null);
    }

    /**
     * @return iterable<int, RemoteDomain>
     */
    public function domains(): iterable
    {
        foreach ($this->pages('QueryDomainList', ['wide' => 1]) as $zeile) {
            $name = $this->readField($zeile, ['domain', 'domainname']);

            if ($name === null) {
                throw new RegistrarException(
                    'Domain-Reselling hat einen Eintrag ohne erkennbaren Domainnamen geliefert.',
                    $zeile,
                );
            }

            yield new RemoteDomain(
                name: mb_strtolower($name),
                reference: $this->readField($zeile, ['domainroid', 'roid']),
                status: $this->readField($zeile, ['status']) ?? 'unknown',
                registeredOn: $this->readDate($zeile, ['creationdate', 'createddate']),
                expiresOn: $this->readDate($zeile, ['expirationdate', 'paiduntildate']),
                autoRenew: ($this->readField($zeile, ['renewalmode']) ?? '') === 'AUTORENEW',
                nameservers: $this->readList($zeile, ['nameserver']),
            );
        }
    }

    /**
     * @return iterable<int, RemoteCertificate>
     */
    public function certificates(): iterable
    {
        foreach ($this->pages('QuerySSLCertList', ['wide' => 1]) as $zeile) {
            // Ausdrücklich ohne `sslcertclass`: das ist die Produktklasse
            // („SSL_CERT_CLASS_…"), nicht der Name, für den das Zertifikat gilt.
            $name = $this->readField($zeile, ['commonname', 'domain', 'sslcertdomain']);

            if ($name === null) {
                throw new RegistrarException(
                    'Domain-Reselling hat einen Zertifikatseintrag ohne erkennbaren Namen geliefert.',
                    $zeile,
                );
            }

            yield new RemoteCertificate(
                commonName: mb_strtolower($name),
                reference: $this->readField($zeile, ['sslcertid', 'certid', 'roid']),
                status: $this->readField($zeile, ['status']) ?? 'unknown',
                issuer: $this->readField($zeile, ['sslcertclass', 'product']),
                issuedOn: $this->readDate($zeile, ['creationdate', 'startdate']),
                expiresOn: $this->readDate($zeile, ['expirationdate', 'enddate']),
                alternativeNames: $this->readList($zeile, ['sslcertsan', 'san']),
            );
        }
    }

    /**
     * Laeuft die Seiten eines Kommandos ab.
     *
     * @param  array<string, scalar>  $parameter
     * @return iterable<int, array<string, string>>
     */
    private function pages(string $kommando, array $parameter = []): iterable
    {
        $erste = 0;
        $proSeite = 100;

        do {
            $antwort = $this->call($kommando, $parameter + ['first' => $erste, 'limit' => $proSeite]);
            $zeilen = $this->rows($antwort['properties']);

            yield from $zeilen;

            $gesamt = (int) ($antwort['properties']['total'][0] ?? count($zeilen));
            $erste += count($zeilen);
        } while ($zeilen !== [] && $erste < $gesamt);
    }

    /**
     * Legt die spaltenweise gelieferten Eigenschaften wieder zu Zeilen zusammen.
     *
     * @param  array<string, array<int, string>>  $properties
     * @return array<int, array<string, string>>
     */
    private function rows(array $properties): array
    {
        // `total` und `count` beschreiben die Abfrage, nicht die Eintraege.
        $spalten = array_diff_key($properties, array_flip(['total', 'count', 'first', 'last', 'limit']));

        $anzahl = 0;

        foreach ($spalten as $werte) {
            $anzahl = max($anzahl, count($werte));
        }

        $zeilen = [];

        for ($i = 0; $i < $anzahl; $i++) {
            $zeile = [];

            foreach ($spalten as $name => $werte) {
                if (isset($werte[$i])) {
                    $zeile[$name] = $werte[$i];
                }
            }

            if ($zeile !== []) {
                $zeilen[] = $zeile;
            }
        }

        return $zeilen;
    }

    /**
     * @param  array<string, scalar>  $parameter
     * @return array{code: int, description: string, properties: array<string, array<int, string>>}
     */
    private function call(string $kommando, array $parameter): array
    {
        $endpunkt = $this->config['endpoint'] ?? 'https://api.domainreselling.de/api/call.cgi';

        try {
            $antwort = Http::timeout(30)->asForm()->post($endpunkt, [
                's_login' => $this->config['username'] ?? '',
                's_pw' => $this->config['password'] ?? '',
                'command' => $kommando,
            ] + $parameter);
        } catch (Throwable $fehler) {
            throw new RegistrarException(
                "Die Verbindung zu Domain-Reselling ist fehlgeschlagen: {$fehler->getMessage()}",
            );
        }

        $ergebnis = $this->parseResponse($antwort->body());

        if ($ergebnis['code'] !== 200) {
            throw new RegistrarException(
                sprintf(
                    'Domain-Reselling meldet zu %s: %s (%d)',
                    $kommando,
                    $ergebnis['description'],
                    $ergebnis['code'],
                ),
                $ergebnis['properties'],
            );
        }

        return $ergebnis;
    }

    /**
     * @return array{code: int, description: string, properties: array<string, array<int, string>>}
     */
    private function parseResponse(string $koerper): array
    {
        $code = 0;
        $beschreibung = '';
        $eigenschaften = [];

        foreach (preg_split('/\R/', $koerper) ?: [] as $zeile) {
            $zeile = trim($zeile);

            if ($zeile === '' || $zeile === 'EOF' || $zeile === '[RESPONSE]') {
                continue;
            }

            if (preg_match('/^property\[([^\]]+)\]\[(\d+)\]\s*=\s*(.*)$/i', $zeile, $treffer) === 1) {
                $eigenschaften[mb_strtolower($treffer[1])][(int) $treffer[2]] = trim($treffer[3]);

                continue;
            }

            if (preg_match('/^code\s*=\s*(\d+)$/i', $zeile, $treffer) === 1) {
                $code = (int) $treffer[1];

                continue;
            }

            if (preg_match('/^description\s*=\s*(.*)$/i', $zeile, $treffer) === 1) {
                $beschreibung = trim($treffer[1]);
            }
        }

        if ($code === 0) {
            throw new RegistrarException(
                'Domain-Reselling hat keine verwertbare Antwort geliefert.',
                mb_substr($koerper, 0, 500),
            );
        }

        return ['code' => $code, 'description' => $beschreibung, 'properties' => $eigenschaften];
    }

    /**
     * @param  array<string, string>  $zeile
     * @param  array<int, string>  $namen
     */
    private function readField(array $zeile, array $namen): ?string
    {
        foreach ($namen as $name) {
            $wert = $zeile[mb_strtolower($name)] ?? null;

            if (is_string($wert) && $wert !== '') {
                return $wert;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $zeile
     * @param  array<int, string>  $namen
     */
    private function readDate(array $zeile, array $namen): ?CarbonImmutable
    {
        $wert = $this->readField($zeile, $namen);

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
     * @param  array<string, string>  $zeile
     * @param  array<int, string>  $namen
     * @return array<int, string>
     */
    private function readList(array $zeile, array $namen): array
    {
        $wert = $this->readField($zeile, $namen);

        return $wert === null ? [] : array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $wert) ?: [])));
    }
}
