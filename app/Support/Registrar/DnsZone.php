<?php

namespace App\Support\Registrar;

use Carbon\CarbonImmutable;

/**
 * Die DNS-Zone einer Domain, wie ein Registrar sie liefert.
 *
 * Bewusst ein eigener Typ und kein Array: was aus einer fremden Schnittstelle
 * kommt, soll genau einmal geprueft werden — hier — und danach im Rest der
 * Anwendung verlaesslich sein. Dieselbe Begruendung wie bei RemoteDomain.
 */
final readonly class DnsZone
{
    /**
     * @param  array<int, string>  $nameservers
     * @param  array<int, DnsRecord>  $records
     * @param  int|null  $ttl  Die Vorgabe der Zone, falls der Anbieter sie nennt.
     * @param  string|null  $soaEmail  Der Zonenkontakt.
     * @param  CarbonImmutable|null  $updatedAt  Wann der Anbieter die Zone zuletzt geaendert hat.
     */
    public function __construct(
        public string $origin,
        public array $nameservers = [],
        public array $records = [],
        public ?int $ttl = null,
        public ?string $soaEmail = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * Die Eintraege nach Typ und Name sortiert.
     *
     * Die Reihenfolge der Schnittstelle ist beliebig; eine feste Sortierung
     * macht die Anzeige lesbar und zwei Abrufe vergleichbar.
     *
     * @return array<int, DnsRecord>
     */
    public function sortedRecords(): array
    {
        $records = $this->records;

        usort($records, function (DnsRecord $links, DnsRecord $rechts): int {
            return [$links->type, $links->name, $links->content]
                <=> [$rechts->type, $rechts->name, $rechts->content];
        });

        return $records;
    }

    /**
     * Wie viele Eintraege es je Typ gibt — fuer die Kurzfassung ueber der Liste.
     *
     * @return array<string, int>
     */
    public function countsByType(): array
    {
        $zaehler = [];

        foreach ($this->records as $record) {
            $zaehler[$record->type] = ($zaehler[$record->type] ?? 0) + 1;
        }

        ksort($zaehler);

        return $zaehler;
    }
}
