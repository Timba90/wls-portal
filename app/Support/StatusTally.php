<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Zaehlt die Treffer einer Abfrage in einem Zug je Status.
 *
 * Die Statusleisten der Listen brauchten sonst eine Abfrage je Schaltflaeche.
 * Bei den Projekten mit sechs Status waren das acht Abfragen fuer eine einzige
 * Leiste — und mit jedem neuen Status waere eine weitere dazugekommen.
 *
 * Gezaehlt wird auf dem Basis-Builder, damit der Enum-Cast der Statusspalte
 * die Schluessel nicht in Enum-Instanzen verwandelt. Globale Scopes gibt es in
 * diesem Projekt nicht; kaeme einer dazu, muesste das hier mitgedacht werden.
 */
final class StatusTally
{
    /**
     * @param  array<string, int>  $counts
     */
    private function __construct(private readonly array $counts) {}

    /**
     * @param  Builder<*>  $query
     */
    public static function from(Builder $query, string $column = 'status'): self
    {
        $gezaehlt = $query->toBase()
            ->select($column)
            ->selectRaw('count(*) as anzahl')
            ->groupBy($column)
            ->pluck('anzahl', $column)
            ->map(fn (mixed $anzahl): int => (int) $anzahl)
            ->all();

        return new self($gezaehlt);
    }

    /**
     * Summe ueber die genannten Status. Ohne Angabe alle zusammen.
     */
    public function of(string ...$status): int
    {
        if ($status === []) {
            return $this->total();
        }

        return array_sum(array_map(fn (string $wert): int => $this->counts[$wert] ?? 0, $status));
    }

    public function total(): int
    {
        return array_sum($this->counts);
    }
}
