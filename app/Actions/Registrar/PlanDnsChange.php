<?php

namespace App\Actions\Registrar;

use App\Enums\DnsChangeOperation;
use App\Models\Domain;
use App\Support\Registrar\DnsChangePlan;
use App\Support\Registrar\DnsRecord;
use App\Support\Registrar\DnsZone;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use App\Support\Registrar\ZoneWriter;

/**
 * Plant eine Aenderung an einer DNS-Zone, ohne etwas zu aendern.
 *
 * Der erste der zwei Schritte. Er liest die Zone, sucht den gemeinten Eintrag
 * und rechnet aus, was danach dort stehen soll — samt einer Pruefsumme ueber
 * genau diesen Stand. Ausgefuehrt wird nichts.
 *
 * Das Suchen ist der eigentliche Inhalt: an einem Namen koennen mehrere
 * Eintraege desselben Typs liegen — ein SPF-Eintrag neben einem
 * Bestaetigungs-Token von Google, mehrere A-Adressen, mehrere MX. „Der
 * TXT-Eintrag an der Domain" ist deshalb oft keine eindeutige Angabe, und diese
 * Aktion sagt das, statt sich einen auszusuchen.
 *
 * @phpstan-type DnsChangeInput array{
 *     vorgang: string,
 *     name?: ?string,
 *     typ: string,
 *     inhalt?: ?string,
 *     alt_inhalt?: ?string,
 *     ttl?: ?int,
 *     prioritaet?: ?int,
 * }
 */
class PlanDnsChange
{
    public function __construct(
        private readonly ReadDnsZone $zoneLesen,
        private readonly RegistrarClientFactory $factory,
    ) {}

    /**
     * @param  DnsChangeInput  $eingabe
     * @param  bool  $frisch  Die Zone neu lesen statt den vorgehaltenen Stand zu nehmen.
     *
     * @throws RegistrarException
     */
    public function __invoke(Domain $domain, array $eingabe, bool $frisch = false): DnsChangePlan
    {
        $this->guardWritable($domain);

        $vorgang = DnsChangeOperation::tryFrom((string) ($eingabe['vorgang'] ?? ''));

        if (! $vorgang instanceof DnsChangeOperation) {
            throw new RegistrarException(sprintf(
                'Unbekannter Vorgang. Möglich sind: %s.',
                implode(', ', DnsChangeOperation::values()),
            ));
        }

        $zone = ($this->zoneLesen)($domain, $frisch);

        $name = $this->recordName((string) ($eingabe['name'] ?? '@'), $zone);
        $typ = mb_strtoupper(trim((string) $eingabe['typ']));

        if ($typ === '') {
            throw new RegistrarException('Ohne Typ (A, AAAA, CNAME, MX, TXT, …) lässt sich kein Eintrag bestimmen.');
        }

        $geschwister = $zone->recordsAt($name, $typ);

        $inhalt = $this->content($eingabe, $vorgang);

        $vorher = $vorgang->needsExisting()
            ? $this->ziel($geschwister, $eingabe, $zone, $name, $typ)
            : null;

        $nachher = match ($vorgang) {
            DnsChangeOperation::Loeschen => null,
            DnsChangeOperation::Aendern => $vorher?->withContent(
                $inhalt ?? '',
                $this->zahl($eingabe, 'ttl'),
                $this->zahl($eingabe, 'prioritaet'),
            ),
            DnsChangeOperation::Anlegen => new DnsRecord(
                name: $name,
                type: $typ,
                content: $inhalt ?? '',
                ttl: $this->zahl($eingabe, 'ttl') ?? $zone->ttl,
                priority: $this->zahl($eingabe, 'prioritaet'),
            ),
        };

        $this->guardNotAlreadyThere($vorgang, $geschwister, $nachher, $zone);
        $this->guardChanges($vorgang, $vorher, $nachher);

        return new DnsChangePlan(
            operation: $vorgang,
            zone: $zone,
            recordName: $name,
            recordType: $typ,
            before: $vorher,
            after: $nachher,
            siblings: $geschwister,
            fingerprint: $this->fingerprint($domain, $zone, $vorgang, $name, $typ, $geschwister, $vorher, $nachher),
        );
    }

    /**
     * Die Pruefsumme ueber Vorgang und Stand.
     *
     * Sie deckt genau die Stelle ab, die angefasst wird: alle Eintraege mit
     * diesem Namen und Typ, dazu der geplante Vorgang. Eine Aenderung an einer
     * anderen Stelle der Zone macht den Plan nicht ungueltig — eine an dieser
     * schon, und darauf kommt es an.
     *
     * @param  array<int, DnsRecord>  $geschwister
     */
    public function fingerprint(
        Domain $domain,
        DnsZone $zone,
        DnsChangeOperation $vorgang,
        string $name,
        string $typ,
        array $geschwister,
        ?DnsRecord $vorher,
        ?DnsRecord $nachher,
    ): string {
        $stand = array_map(
            fn (DnsRecord $record): string => implode('|', [
                $record->name, $record->type, $record->content,
                (string) $record->ttl, (string) $record->priority, (string) $record->reference,
            ]),
            $geschwister,
        );

        sort($stand);

        return hash('sha256', (string) json_encode([
            'anbieter' => $domain->provider->value,
            'zone' => $zone->origin,
            'vorgang' => $vorgang->value,
            'name' => $name,
            'typ' => $typ,
            'stand' => $stand,
            'vorher' => $vorher?->describe(),
            'nachher' => $nachher?->describe(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Der gemeinte Eintrag aus den Eintraegen an derselben Stelle.
     *
     * @param  array<int, DnsRecord>  $geschwister
     * @param  DnsChangeInput  $eingabe
     */
    private function ziel(array $geschwister, array $eingabe, DnsZone $zone, string $name, string $typ): DnsRecord
    {
        if ($geschwister === []) {
            throw new RegistrarException(sprintf(
                'In der Zone von %s gibt es an „%s" keinen %s-Eintrag. Zum Anlegen ist „anlegen" der Vorgang.',
                $zone->origin,
                $name,
                $typ,
            ));
        }

        $altInhalt = $this->text($eingabe, 'alt_inhalt');

        if ($altInhalt !== null) {
            $treffer = array_values(array_filter(
                $geschwister,
                fn (DnsRecord $record): bool => $this->gleich($record->content, $altInhalt),
            ));

            if (count($treffer) === 1) {
                return $treffer[0];
            }

            if ($treffer === []) {
                throw new RegistrarException(sprintf(
                    'An „%s" gibt es keinen %s-Eintrag mit diesem Inhalt. Vorhanden ist: %s',
                    $name,
                    $typ,
                    $this->auflisten($geschwister),
                ));
            }

            throw new RegistrarException(sprintf(
                'An „%s" liegen mehrere %s-Einträge mit demselben Inhalt: %s. So lässt sich keiner bestimmen.',
                $name,
                $typ,
                $this->auflisten($treffer),
            ));
        }

        if (count($geschwister) === 1) {
            return $geschwister[0];
        }

        throw new RegistrarException(sprintf(
            'An „%s" liegen %d %s-Einträge. Welcher gemeint ist, muss „alt_inhalt" sagen. Vorhanden ist: %s',
            $name,
            count($geschwister),
            $typ,
            $this->auflisten($geschwister),
        ));
    }

    /**
     * Ein Eintrag, der schon genau so dasteht, wird nicht noch einmal angelegt.
     *
     * Sonst entstuende ein zweiter gleicher — bei SPF hiesse das: zwei
     * SPF-Eintraege, und damit gar keiner, der gilt.
     *
     * @param  array<int, DnsRecord>  $geschwister
     */
    private function guardNotAlreadyThere(
        DnsChangeOperation $vorgang,
        array $geschwister,
        ?DnsRecord $nachher,
        DnsZone $zone,
    ): void {
        if ($vorgang !== DnsChangeOperation::Anlegen || $nachher === null) {
            return;
        }

        foreach ($geschwister as $record) {
            if ($this->gleich($record->content, $nachher->content)) {
                throw new RegistrarException(sprintf(
                    'In der Zone von %s steht dieser Eintrag schon genau so: %s',
                    $zone->origin,
                    $record->describe(),
                ));
            }
        }
    }

    /**
     * Eine Aenderung, die nichts aendert, ist keine.
     */
    private function guardChanges(DnsChangeOperation $vorgang, ?DnsRecord $vorher, ?DnsRecord $nachher): void
    {
        if ($vorgang !== DnsChangeOperation::Aendern || $vorher === null || $nachher === null) {
            return;
        }

        if ($this->gleich($vorher->content, $nachher->content)
            && $vorher->ttl === $nachher->ttl
            && $vorher->priority === $nachher->priority) {
            throw new RegistrarException(sprintf(
                'Der Eintrag steht schon so da: %s',
                $vorher->describe(),
            ));
        }
    }

    private function guardWritable(Domain $domain): void
    {
        $client = $this->factory->for($domain->provider);

        if (! $client instanceof ZoneWriter) {
            throw new RegistrarException(sprintf(
                'Der Anschluss %s kann keine DNS-Einträge ändern.',
                $domain->provider->label(),
            ));
        }

        if (! $client->canWriteZone()) {
            throw new RegistrarException(sprintf(
                'Für %s sind schreibende DNS-Änderungen nicht möglich: entweder fehlen die Zugangsdaten, '
                .'oder sie sind in dieser Umgebung nicht eingeschaltet (REGISTRAR_DNS_WRITES_ENABLED).',
                $domain->provider->label(),
            ));
        }
    }

    /**
     * Der Name relativ zur Zone, in derselben Schreibweise wie beim Lesen.
     */
    private function recordName(string $name, DnsZone $zone): string
    {
        $name = mb_strtolower(rtrim(trim($name), '.'));

        if ($name === '' || $name === '@' || $name === $zone->origin) {
            return '@';
        }

        if (str_ends_with($name, '.'.$zone->origin)) {
            return mb_substr($name, 0, -mb_strlen('.'.$zone->origin));
        }

        return $name;
    }

    /**
     * @param  DnsChangeInput  $eingabe
     */
    private function content(array $eingabe, DnsChangeOperation $vorgang): ?string
    {
        $inhalt = $this->text($eingabe, 'inhalt');

        if ($vorgang->needsContent() && $inhalt === null) {
            throw new RegistrarException('Ohne Inhalt lässt sich kein Eintrag setzen.');
        }

        return $inhalt;
    }

    /**
     * Vergleich zweier Inhalte.
     *
     * TXT-Werte kommen bei einem Anbieter in Anfuehrungszeichen und beim
     * anderen ohne, und abgetippte Werte tragen gern doppelte Leerzeichen.
     * Beides soll denselben Eintrag treffen; der Inhalt selbst wird deshalb
     * nur zum *Vergleichen* vereinheitlicht, nie zum Schreiben.
     */
    private function gleich(string $links, string $rechts): bool
    {
        return $this->normalisieren($links) === $this->normalisieren($rechts);
    }

    private function normalisieren(string $wert): string
    {
        $wert = trim($wert);

        if (str_starts_with($wert, '"') && str_ends_with($wert, '"') && mb_strlen($wert) > 1) {
            $wert = mb_substr($wert, 1, -1);
        }

        return (string) preg_replace('/\s+/', ' ', trim($wert));
    }

    /**
     * @param  array<int, DnsRecord>  $records
     */
    private function auflisten(array $records): string
    {
        return implode('; ', array_map(fn (DnsRecord $record): string => $record->describe(), $records));
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function text(array $eingabe, string $feld): ?string
    {
        $wert = $eingabe[$feld] ?? null;

        return is_scalar($wert) && trim((string) $wert) !== '' ? trim((string) $wert) : null;
    }

    /**
     * @param  array<string, mixed>  $eingabe
     */
    private function zahl(array $eingabe, string $feld): ?int
    {
        $wert = $eingabe[$feld] ?? null;

        return is_numeric($wert) ? (int) $wert : null;
    }
}
