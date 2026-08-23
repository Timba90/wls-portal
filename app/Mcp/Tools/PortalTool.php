<?php

namespace App\Mcp\Tools;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Tool;

/**
 * Gemeinsame Grundlage aller Portal-Werkzeuge.
 *
 * Buendelt die Darstellung von Betraegen, das Aufloesen von Datensaetzen und
 * die Blaetterlogik, damit die einzelnen Werkzeuge nur ihre Fachlogik tragen.
 */
abstract class PortalTool extends Tool
{
    /**
     * Hoechstzahl an Datensaetzen, die ein Suchwerkzeug zurueckgibt.
     */
    protected const MAX_LIMIT = 100;

    protected const DEFAULT_LIMIT = 25;

    /**
     * Betrag in Cent und in lesbarer Schreibweise.
     *
     * Cent bleibt der fuehrende Wert: die Anwendung rechnet ausschliesslich in
     * ganzen Cent, die formatierte Fassung dient nur der Anzeige.
     *
     * @return array{cents: int, formatiert: string}
     */
    protected function money(int $cents): array
    {
        return [
            'cents' => $cents,
            'formatiert' => Money::fromCents($cents)->format(),
        ];
    }

    /**
     * Begrenzt die angeforderte Menge auf den zulaessigen Bereich.
     */
    protected function limit(?int $requested): int
    {
        return max(1, min($requested ?? self::DEFAULT_LIMIT, self::MAX_LIMIT));
    }

    /**
     * Wendet einen Suchbegriff auf mehrere Spalten an.
     *
     * @param  Builder<covariant Model>  $query
     * @param  array<int, string>  $columns
     */
    protected function applySearch(Builder $query, ?string $term, array $columns): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term, $columns): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    /**
     * Datum als ISO-Zeichenkette oder `null`.
     */
    protected function date(mixed $value): ?string
    {
        return $value?->toDateString();
    }

    /**
     * Zeitpunkt als ISO-Zeichenkette oder `null`.
     */
    protected function dateTime(mixed $value): ?string
    {
        return $value?->toIso8601String();
    }
}
