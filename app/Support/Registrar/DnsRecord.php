<?php

namespace App\Support\Registrar;

/**
 * Ein einzelner Eintrag einer DNS-Zone, wie ein Registrar ihn liefert.
 *
 * Nur zu lesen. Das Portal zeigt den Stand beim Anbieter an; geaendert wird er
 * dort. Ein falsch gesetzter Eintrag schaltet eine Kundenseite oder deren
 * Mailempfang sofort ab — schreibende Aufrufe gibt es deshalb bewusst nicht.
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
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $content,
        public ?int $ttl = null,
        public ?int $priority = null,
        public bool $derived = false,
    ) {}
}
