<?php

namespace App\Support\Registrar;

/**
 * Ein einzelner Eintrag einer DNS-Zone, wie ein Registrar ihn liefert.
 *
 * Ein Wertobjekt ohne eigene Logik: gelesen wird es aus der Zone, geschrieben
 * wird es ueber `ZoneWriter` — und dort nur nach Plan und Bestaetigung. Ein
 * falsch gesetzter Eintrag schaltet eine Kundenseite oder deren Mailempfang
 * sofort ab; die Absicherung liegt deshalb im Schreibpfad und nicht hier.
 */
final readonly class DnsRecord
{
    /**
     * @param  string  $name  Der Name relativ zur Zone. `@` steht fuer den Ursprung.
     * @param  string  $type  A, AAAA, CNAME, MX, TXT, SRV und so weiter.
     * @param  string  $content  Der Wert des Eintrags.
     * @param  int|null  $ttl  Gueltigkeitsdauer in Sekunden, falls der Anbieter sie nennt.
     * @param  int|null  $priority  Nur bei MX und SRV gefuellt.
     * @param  bool  $derived  Kein echter Eintrag der Zone, sondern von ihr abgeleitet.
     * @param  string|null  $reference  Die Kennung des Eintrags beim Anbieter, falls er eine
     *                                  vergibt. ResellerInterface tut es (`id`) und braucht sie
     *                                  zum Aendern und Loeschen; autoDNS kennt keine und trifft
     *                                  seine Eintraege ueber Name, Typ und Wert.
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $content,
        public ?int $ttl = null,
        public ?int $priority = null,
        public bool $derived = false,
        public ?string $reference = null,
    ) {}

    /**
     * Derselbe Eintrag, nur mit anderem Wert.
     *
     * Fuer den Schreibpfad: „dieser Eintrag, aber mit diesem Inhalt" ist die
     * haeufigste Aenderung, und sie soll die Kennung behalten.
     */
    public function withContent(string $content, ?int $ttl = null, ?int $priority = null): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            content: $content,
            ttl: $ttl ?? $this->ttl,
            priority: $priority ?? $this->priority,
            derived: $this->derived,
            reference: $this->reference,
        );
    }

    /**
     * Eine Kurzfassung fuer Anzeige und Protokoll: `www IN CNAME beispiel.de.`
     */
    public function describe(): string
    {
        return trim(sprintf(
            '%s %s%s %s',
            $this->name,
            $this->type,
            $this->priority === null ? '' : ' '.$this->priority,
            $this->content,
        ));
    }
}
