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
    private const ERLAUBTE_AKTIONEN = ['domain/list', 'domain/check', 'tld/list'];

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
                $liste = $this->liste($antwort);

                foreach ($liste as $eintrag) {
                    if (is_array($eintrag)) {
                        yield $this->toDomain($eintrag);
                    }
                }

                // Der Anbieter nennt die Gesamtzahl unter `data.total`; die
                // Seiten laufen, bis alles gesehen wurde.
                $gesamt = (int) data_get($antwort, 'data.total', 0);
                $offset += count($liste);
            } while ($offset < $gesamt);
        }
    }

    /**
     * ResellerInterface fuehrt in diesem Anschluss keine Zertifikate.
     *
     * Die Anleitung nennt fuer den Bestand `domain/*`, `dns/*` und
     * `handle/*`; ein Zertifikatsbestand kommt darin nicht vor. Eine leere
     * Liste ist deshalb die ehrliche Antwort — geraten wird hier nichts.
     * S/MIME-Zertifikate stammen von autoDNS.
     *
     * @return iterable<int, RemoteCertificate>
     */
    public function certificates(): iterable
    {
        return [];
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

        if (! is_array($inhalt) || ($inhalt['success'] ?? false) !== true) {
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

        if ($inhalt['success'] ?? false) {
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

        if (! is_array($inhalt) || ($inhalt['success'] ?? false) !== true) {
            $meldung = is_array($inhalt)
                ? (string) ($inhalt['stateName'] ?? $inhalt['state'] ?? (json_encode($inhalt) ?: 'ohne Meldung'))
                : "HTTP {$antwort->status()}";

            throw $this->fehler($aktion, $meldung, $inhalt);
        }

        return $inhalt;
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
     * Der Anbieter legt sie unter `data.list` ab — bei den Preisen war es
     * genau dieses Feld, waehrend die alte Beispielantwort etwas anderes
     * zeigte. Wir lesen deshalb nur den belegten Weg und brechen sonst ab,
     * statt stillschweigend nichts einzulesen.
     *
     * @param  array<string, mixed>  $antwort
     * @return array<int, mixed>
     */
    private function liste(array $antwort): array
    {
        $liste = data_get($antwort, 'data.list');

        if (! is_array($liste)) {
            throw new RegistrarException(
                'Die Antwort auf domain/list enthält keine Liste unter data.list.',
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
            // Nameserver liegen nicht in `domain/list` — die Zone liest man
            // nur ueber `dns/*`, und das ist bewusst nicht Teil des Imports.
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
