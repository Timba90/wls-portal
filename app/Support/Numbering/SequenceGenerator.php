<?php

namespace App\Support\Numbering;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Vergibt fortlaufende Nummern transaktionssicher.
 *
 * Der Zaehler wird mit `lockForUpdate()` innerhalb einer Transaktion gelesen und
 * erhoeht. Parallele Aufrufe warten dadurch aufeinander, statt dieselbe Nummer
 * zweimal zu vergeben. Da der Zaehler nie zurueckgesetzt wird, kann eine
 * einmal vergebene Nummer auch nach Archivierung nicht erneut auftreten.
 */
class SequenceGenerator
{
    /**
     * Liefert den naechsten Wert der Sequenz und erhoeht den Zaehler.
     */
    public function next(string $key): int
    {
        return DB::transaction(function () use ($key): int {
            $sequence = Sequence::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                // firstOrCreate greift hier nicht: bei einem parallelen Insert
                // gewinnt der Unique-Index, und wir lesen den Datensatz erneut
                // -- diesmal mit Sperre.
                Sequence::query()->insertOrIgnore([
                    'key' => $key,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = Sequence::query()
                    ->where('key', $key)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $value = $sequence->next_value;

            $sequence->forceFill(['next_value' => $value + 1])->save();

            return $value;
        });
    }

    /**
     * Formatiert einen Sequenzwert als Nummer mit Praefix.
     */
    public function format(string $prefix, int $value, int $padding = 5): string
    {
        return $prefix.str_pad((string) $value, $padding, '0', STR_PAD_LEFT);
    }
}
