<?php

namespace App\Support\Registrar;

use App\Enums\DnsChangeOperation;

/**
 * Eine geplante, noch nicht ausgefuehrte Aenderung an einer Zone.
 *
 * Der erste der zwei Schritte: hier steht, was jetzt in der Zone steht, was
 * danach dort stehen soll, und eine Pruefsumme ueber genau diesen Stand. Der
 * zweite Schritt nimmt die Pruefsumme wieder an und fuehrt die Aenderung nur
 * aus, wenn sie noch passt. Damit kann niemand etwas aendern, was er nicht
 * vorher gesehen hat — und nichts, was sich seit dem Hinsehen bewegt hat.
 */
final readonly class DnsChangePlan
{
    /**
     * @param  DnsZone  $zone  Der Stand, gegen den geplant wurde.
     * @param  DnsRecord|null  $before  Der Eintrag, der weichen soll.
     * @param  DnsRecord|null  $after  Der Eintrag, der danach stehen soll.
     * @param  array<int, DnsRecord>  $siblings  Alle Eintraege an derselben Stelle — sie machen den
     *                                           Unterschied zwischen „der Eintrag" und „einer von
     *                                           mehreren" sichtbar.
     */
    public function __construct(
        public DnsChangeOperation $operation,
        public DnsZone $zone,
        public string $recordName,
        public string $recordType,
        public ?DnsRecord $before,
        public ?DnsRecord $after,
        public array $siblings,
        public string $fingerprint,
    ) {}

    /**
     * Die Aenderung in einer Zeile, fuer Protokoll und Anzeige.
     */
    public function describe(): string
    {
        return match (true) {
            $this->before === null => sprintf('neu: %s', $this->after?->describe() ?? '—'),
            $this->after === null => sprintf('entfällt: %s', $this->before->describe()),
            default => sprintf('%s → %s', $this->before->describe(), $this->after->describe()),
        };
    }
}
