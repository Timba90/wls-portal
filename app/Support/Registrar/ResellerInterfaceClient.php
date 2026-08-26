<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Anschluss an ResellerInterface (do.de) ueber die Bruecke auf demselben Host.
 *
 * Das Portal meldet sich **nicht** selbst bei `core.resellerinterface.de` an.
 * Die Bruecke (`domain-api-call`) haelt die Zugangsdaten in ihrer eigenen
 * `.env`, uebernimmt Anmeldung und Sitzung und wird lokal aufgerufen — das
 * Portal laeuft auf demselben Server, ein Umweg ueber SSH entfaellt.
 *
 * Warum so streng: ein selbstgebautes Login-Skript hat sich mit leeren
 * Zugangsdaten dutzendfach angemeldet und das Konto gesperrt; DNS-Aenderungen
 * waren danach fuer alle Kunden blockiert. Daraus folgen drei Regeln, die hier
 * im Code stehen und nicht nur im Kommentar:
 *
 * 1. Nur lesende Aufrufe. Schreibende Aktionen sind gar nicht erst erreichbar.
 * 2. Kein Wiederholen. Ein fehlgeschlagener Aufruf wird nie ein zweites Mal
 *    versucht — jeder weitere Versuch verlaengert eine Sperre.
 * 3. Bei `TOO_MANY_ATTEMPTS` oder `WRONG_USERNAME_OR_PASSWORD` bricht der
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
    private const ERLAUBTE_AKTIONEN = ['domain/list', 'domain/check'];

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
     * @param  array{command?: string, reseller_ids?: string, test_domain?: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function provider(): RegistrarProvider
    {
        return RegistrarProvider::ResellerInterface;
    }

    /**
     * Eingerichtet ist der Anschluss, wenn die Bruecke hier ausfuehrbar ist.
     *
     * Zugangsdaten pruefen wir nicht — wir haben sie nicht und sollen sie
     * nicht haben.
     */
    public function isConfigured(): bool
    {
        $programm = $this->command();

        return $programm !== '' && is_executable($programm);
    }

    public function testConnection(): string
    {
        $this->guardConfigured();

        $domain = $this->config['test_domain'] ?? 'wls-portal-verbindungstest-xyz.de';

        $antwort = $this->call('domain/check', ['domain' => $domain]);

        return sprintf(
            'ResellerInterface hat geantwortet: %s. Geprüft wurde nur die Verfügbarkeit von %s — gelesen oder geändert wurde nichts.',
            $this->text($antwort, 'stateName') ?? $this->text($antwort, 'state') ?? 'ohne Statusangabe',
            $domain,
        );
    }

    /**
     * @return iterable<int, RemoteDomain>
     */
    public function domains(): iterable
    {
        $this->guardConfigured();

        foreach ($this->resellerIds() as $resellerId) {
            $params = ['limit' => self::SEITENGROESSE];

            if ($resellerId !== null) {
                $params['resellerID'] = $resellerId;
            }

            foreach ($this->liste($this->call('domain/list', $params)) as $eintrag) {
                yield $this->toDomain($eintrag);
            }
        }
    }

    /**
     * ResellerInterface fuehrt in diesem Anschluss keine Zertifikate.
     *
     * Die Anleitung nennt fuer den Bestand `domain/*`, `dns/*` und
     * `handle/*`; ein Zertifikatsbestand kommt darin nicht vor. Eine leere
     * Liste ist deshalb die ehrliche Antwort — geraten wird hier nichts.
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
     * `null` steht fuer den Hauptaccount: ohne `resellerID` liefert
     * `domain/list` genau ihn — und eben nur ihn.
     *
     * @return array<int, string|null>
     */
    private function resellerIds(): array
    {
        $weitere = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($this->config['reseller_ids'] ?? '')),
        ), fn (string $id): bool => $id !== ''));

        return [null, ...$weitere];
    }

    /**
     * Ein einzelner Aufruf der Bruecke. Ohne Wiederholung, mit Absicht.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $aktion, array $params): array
    {
        if (! in_array($aktion, self::ERLAUBTE_AKTIONEN, strict: true)) {
            throw new RegistrarException(
                "Die Aktion {$aktion} ist für diesen Anschluss nicht vorgesehen. Er liest nur.",
            );
        }

        $auftrag = json_encode(['action' => $aktion, 'params' => $params], JSON_UNESCAPED_SLASHES);

        if ($auftrag === false) {
            throw new RegistrarException("Der Aufruf {$aktion} liess sich nicht als JSON schreiben.");
        }

        try {
            $ergebnis = Process::timeout(120)->run([$this->command(), 'call', $auftrag]);
        } catch (Throwable $fehler) {
            throw new RegistrarException(
                "Die Brücke liess sich nicht aufrufen: {$fehler->getMessage()}",
            );
        }

        if (! $ergebnis->successful()) {
            throw $this->fehler($aktion, trim($ergebnis->errorOutput()) ?: trim($ergebnis->output()));
        }

        $inhalt = json_decode($ergebnis->output(), true);

        if (! is_array($inhalt)) {
            throw new RegistrarException(
                "Die Brücke hat auf {$aktion} keine verwertbare Antwort geliefert.",
                mb_substr($ergebnis->output(), 0, 500),
            );
        }

        return $this->guardAntwort($aktion, $inhalt);
    }

    /**
     * Prueft den Umschlag der Antwort.
     *
     * @param  array<string, mixed>  $inhalt
     * @return array<string, mixed>
     */
    private function guardAntwort(string $aktion, array $inhalt): array
    {
        $meldung = $this->text($inhalt, 'stateName')
            ?? $this->text($inhalt, 'state')
            ?? $this->text($inhalt, 'message')
            ?? '';

        if ($inhalt['success'] ?? false) {
            return $inhalt;
        }

        if ($meldung === '') {
            $meldung = json_encode($inhalt) ?: '';
        }

        throw $this->fehler($aktion, $meldung, $inhalt);
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
     * @return array<int, array<string, mixed>>
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

        return array_values(array_filter($liste, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function toDomain(array $eintrag): RemoteDomain
    {
        $name = $this->text($eintrag, 'domain') ?? $this->text($eintrag, 'name');

        if ($name === null) {
            throw new RegistrarException(
                'ResellerInterface hat einen Domaineintrag ohne Namen geliefert.',
                $eintrag,
            );
        }

        return new RemoteDomain(
            name: mb_strtolower($name),
            reference: $this->text($eintrag, 'domainID') ?? $this->text($eintrag, 'id'),
            status: $this->text($eintrag, 'status') ?? $this->text($eintrag, 'stateName') ?? 'unknown',
            registeredOn: $this->date($eintrag, 'registered') ?? $this->date($eintrag, 'created'),
            expiresOn: $this->date($eintrag, 'expire') ?? $this->date($eintrag, 'expireDate') ?? $this->date($eintrag, 'payedUntil'),
            autoRenew: $this->flag($eintrag, 'autoRenew'),
            nameservers: $this->nameservers($eintrag),
        );
    }

    /**
     * @param  array<string, mixed>  $eintrag
     * @return array<int, string>
     */
    private function nameservers(array $eintrag): array
    {
        $liste = $eintrag['nameserver'] ?? $eintrag['nameservers'] ?? [];

        if (! is_array($liste)) {
            return [];
        }

        $namen = [];

        foreach ($liste as $element) {
            if (is_array($element)) {
                $name = $this->text($element, 'name') ?? $this->text($element, 'nameserver');

                if ($name !== null) {
                    $namen[] = $name;
                }

                continue;
            }

            if (is_scalar($element) && (string) $element !== '') {
                $namen[] = (string) $element;
            }
        }

        return $namen;
    }

    private function command(): string
    {
        return (string) ($this->config['command'] ?? '');
    }

    private function guardConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RegistrarException(sprintf(
                'Die Brücke zu ResellerInterface ist unter %s nicht ausführbar. Ohne sie ist kein Zugriff möglich — '
                .'ein eigener Login beim Anbieter kommt nicht in Frage.',
                $this->command() !== '' ? $this->command() : 'dem eingestellten Pfad',
            ));
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
    private function flag(array $eintrag, string $feld): bool
    {
        // Streng verglichen: „0" und „false" sollen nicht als Ja durchgehen.
        return in_array($eintrag[$feld] ?? null, [true, 1, '1', 'true', 'TRUE', 'yes'], strict: true);
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function date(array $eintrag, string $feld): ?CarbonImmutable
    {
        $wert = $this->text($eintrag, $feld);

        if ($wert === null || $wert === '0000-00-00' || $wert === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return CarbonImmutable::parse($wert);
        } catch (Throwable) {
            return null;
        }
    }
}
