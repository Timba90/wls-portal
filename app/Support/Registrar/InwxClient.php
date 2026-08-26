<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Anschluss an die DomRobot-Schnittstelle von INWX.
 *
 * INWX spricht JSON-RPC: ein POST je Aufruf, `method` und `params`, Antwort
 * mit `code`, `msg` und `resData`. 1000 heisst erfolgreich. Die Sitzung haengt
 * an einem Cookie, das `account.login` setzt — deshalb geht jeder Import ueber
 * dieselbe Instanz.
 *
 * Bewusst ohne das Paket `inwx/domrobot`: der Aufruf ist ein POST mit JSON,
 * und eine zusaetzliche Abhaengigkeit will genehmigt sein.
 *
 * Die Feldnamen der Antwort sind gegen die Dokumentation geschrieben, aber
 * nicht gegen ein echtes Konto geprueft — deshalb liest `readField()` mehrere
 * gebraeuchliche Schreibweisen, und `domains()` bricht mit der Rohantwort ab,
 * wenn nichts davon passt. Lieber ein klarer Abbruch als ein stiller Import
 * leerer Datensaetze.
 */
class InwxClient implements RegistrarClient
{
    private bool $angemeldet = false;

    /**
     * Cookies der Sitzung, die `account.login` setzt.
     *
     * @var array<string, string>
     */
    private array $cookies = [];

    /**
     * @param  array{endpoint?: string, username?: string, password?: string, shared_secret?: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function provider(): RegistrarProvider
    {
        return RegistrarProvider::Inwx;
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
        foreach ($this->pages('domain.list') as $eintrag) {
            $name = $this->readField($eintrag, ['domain', 'domainName', 'name']);

            if ($name === null) {
                throw new RegistrarException(
                    'INWX hat einen Domaineintrag ohne erkennbaren Namen geliefert. '
                    .'Vermutlich haben sich die Feldnamen geändert.',
                    $eintrag,
                );
            }

            yield new RemoteDomain(
                name: mb_strtolower($name),
                reference: $this->readField($eintrag, ['roId', 'id']),
                status: $this->readField($eintrag, ['status']) ?? 'unknown',
                registeredOn: $this->readDate($eintrag, ['crDate', 'createdDate', 'registeredDate']),
                expiresOn: $this->readDate($eintrag, ['exDate', 'expirationDate', 'expiryDate']),
                autoRenew: ($this->readField($eintrag, ['renewalMode', 'renewal_mode']) ?? '') === 'AUTORENEW',
                nameservers: $this->readList($eintrag, ['ns', 'nameserver', 'nameservers']),
            );
        }
    }

    /**
     * @return iterable<int, RemoteCertificate>
     */
    public function certificates(): iterable
    {
        foreach ($this->pages('certificate.list') as $eintrag) {
            $name = $this->readField($eintrag, ['commonName', 'common_name', 'domain', 'name']);

            if ($name === null) {
                throw new RegistrarException(
                    'INWX hat einen Zertifikatseintrag ohne erkennbaren Namen geliefert.',
                    $eintrag,
                );
            }

            yield new RemoteCertificate(
                commonName: mb_strtolower($name),
                reference: $this->readField($eintrag, ['id', 'certificateId', 'roId']),
                status: $this->readField($eintrag, ['status']) ?? 'unknown',
                issuer: $this->readField($eintrag, ['issuer', 'ca', 'product']),
                issuedOn: $this->readDate($eintrag, ['startDate', 'issueDate', 'crDate']),
                expiresOn: $this->readDate($eintrag, ['endDate', 'expirationDate', 'exDate']),
                alternativeNames: $this->readList($eintrag, ['san', 'alternativeNames', 'hostnames']),
            );
        }
    }

    /**
     * Laeuft die Seiten einer Listenmethode ab.
     *
     * @return iterable<int, array<string, mixed>>
     */
    private function pages(string $methode): iterable
    {
        $this->login();

        $seite = 1;
        $proSeite = 100;
        $gesehen = 0;

        do {
            $antwort = $this->call($methode, ['page' => $seite, 'pagelimit' => $proSeite]);

            $eintraege = $this->extractList($antwort);
            $gesamt = (int) ($antwort['count'] ?? count($eintraege));

            foreach ($eintraege as $eintrag) {
                if (is_array($eintrag)) {
                    yield $eintrag;
                }
            }

            $gesehen += count($eintraege);
            $seite++;
        } while ($eintraege !== [] && $gesehen < $gesamt);
    }

    /**
     * Die Liste aus `resData` holen, ohne den Schluesselnamen zu erraten.
     *
     * @param  array<string, mixed>  $resData
     * @return array<int, mixed>
     */
    private function extractList(array $resData): array
    {
        foreach (['domain', 'certificate', 'list', 'data'] as $schluessel) {
            if (isset($resData[$schluessel]) && is_array($resData[$schluessel])) {
                return array_values($resData[$schluessel]);
            }
        }

        return [];
    }

    private function login(): void
    {
        if ($this->angemeldet) {
            return;
        }

        $antwort = $this->call('account.login', [
            'user' => $this->config['username'] ?? '',
            'pass' => $this->config['password'] ?? '',
        ]);

        // Bei aktivierter Zwei-Faktor-Anmeldung verlangt INWX einen zweiten
        // Schritt. Ohne hinterlegtes Geheimnis kommen wir hier nicht weiter,
        // und das soll deutlich gesagt werden.
        if (($antwort['tfa'] ?? '') !== '' && $antwort['tfa'] !== '0') {
            $geheimnis = $this->config['shared_secret'] ?? null;

            if (! filled($geheimnis)) {
                throw new RegistrarException(
                    'Das INWX-Konto verlangt eine Zwei-Faktor-Anmeldung. '
                    .'Ohne INWX_SHARED_SECRET ist kein Import möglich.',
                );
            }

            $this->call('account.unlock', ['tan' => $this->totp($geheimnis)]);
        }

        $this->angemeldet = true;
    }

    /**
     * Ein Aufruf gegen die JSON-RPC-Schnittstelle.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed> Der Inhalt von `resData`.
     */
    private function call(string $methode, array $params): array
    {
        try {
            $antwort = $this->request()->post('', [
                'method' => $methode,
                'params' => $params,
            ]);
        } catch (Throwable $fehler) {
            throw new RegistrarException(
                "Die Verbindung zu INWX ist fehlgeschlagen: {$fehler->getMessage()}",
            );
        }

        foreach ($antwort->cookies()->toArray() as $cookie) {
            $this->cookies[$cookie['Name']] = $cookie['Value'];
        }

        $inhalt = $antwort->json();

        if (! is_array($inhalt) || ! isset($inhalt['code'])) {
            throw new RegistrarException(
                "INWX hat auf {$methode} keine verwertbare Antwort geliefert.",
                is_string($antwort->body()) ? mb_substr($antwort->body(), 0, 500) : null,
            );
        }

        // 1000 ist Erfolg, 1001 „Erfolg, aber Bestätigung nötig".
        if (! in_array((int) $inhalt['code'], [1000, 1001], true)) {
            throw new RegistrarException(
                sprintf('INWX meldet zu %s: %s (%s)', $methode, $inhalt['msg'] ?? 'ohne Meldung', $inhalt['code']),
                $inhalt,
            );
        }

        return is_array($inhalt['resData'] ?? null) ? $inhalt['resData'] : [];
    }

    private function request(): PendingRequest
    {
        $endpunkt = $this->config['endpoint'] ?? 'https://api.domrobot.com/jsonrpc/';

        return Http::baseUrl($endpunkt)
            ->timeout(30)
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->cookies === [] ? [] : [
                'Cookie' => collect($this->cookies)
                    ->map(fn (string $wert, string $name): string => "{$name}={$wert}")
                    ->implode('; '),
            ]);
    }

    /**
     * Zeitbasiertes Einmalkennwort nach RFC 6238, wie INWX es erwartet.
     */
    private function totp(string $sharedSecret): string
    {
        $schluessel = $this->base32Decode($sharedSecret);
        $zeitfenster = pack('N*', 0).pack('N*', (int) floor(time() / 30));
        $hash = hash_hmac('sha1', $zeitfenster, $schluessel, true);
        $versatz = ord($hash[19]) & 0xF;

        $wert = ((ord($hash[$versatz]) & 0x7F) << 24)
            | ((ord($hash[$versatz + 1]) & 0xFF) << 16)
            | ((ord($hash[$versatz + 2]) & 0xFF) << 8)
            | (ord($hash[$versatz + 3]) & 0xFF);

        return str_pad((string) ($wert % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $eingabe): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $eingabe = rtrim(mb_strtoupper($eingabe), '=');
        $bits = '';

        foreach (str_split($eingabe) as $zeichen) {
            $stelle = strpos($alphabet, $zeichen);

            if ($stelle === false) {
                continue;
            }

            $bits .= str_pad(decbin($stelle), 5, '0', STR_PAD_LEFT);
        }

        $ausgabe = '';

        foreach (str_split($bits, 8) as $achtergruppe) {
            if (mb_strlen($achtergruppe) === 8) {
                $ausgabe .= chr((int) bindec($achtergruppe));
            }
        }

        return $ausgabe;
    }

    /**
     * @param  array<string, mixed>  $eintrag
     * @param  array<int, string>  $namen
     */
    private function readField(array $eintrag, array $namen): ?string
    {
        foreach ($namen as $name) {
            $wert = $eintrag[$name] ?? null;

            if (is_scalar($wert) && (string) $wert !== '') {
                return (string) $wert;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $eintrag
     * @param  array<int, string>  $namen
     */
    private function readDate(array $eintrag, array $namen): ?CarbonImmutable
    {
        $wert = $this->readField($eintrag, $namen);

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
     * @param  array<string, mixed>  $eintrag
     * @param  array<int, string>  $namen
     * @return array<int, string>
     */
    private function readList(array $eintrag, array $namen): array
    {
        foreach ($namen as $name) {
            $wert = $eintrag[$name] ?? null;

            if (is_array($wert)) {
                return array_values(array_filter(array_map(
                    fn (mixed $eintrag): string => is_scalar($eintrag) ? (string) $eintrag : '',
                    $wert,
                )));
            }

            if (is_string($wert) && $wert !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $wert))));
            }
        }

        return [];
    }
}
