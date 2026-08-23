<?php

namespace App\Actions\Pricing;

use App\Models\PriceChange;

/**
 * Setzt alle faelligen Preisaenderungen wirksam.
 *
 * Wird taeglich vom Scheduler aufgerufen, damit geplante Aenderungen zum
 * Wirksamkeitsdatum automatisch greifen.
 */
class ApplyDuePriceChanges
{
    public function __construct(private readonly ApplyPriceChange $applyPriceChange) {}

    /**
     * @return array{applied: int, lapsed: int} Wirksam gewordene und verfallene
     *                                          Aenderungen, getrennt gezaehlt
     */
    public function __invoke(): array
    {
        $wirksam = 0;
        $verfallen = 0;

        // Bewusst kein chunkById: dessen Cursor laeuft ueber die ID, waehrend
        // die fachlich richtige Reihenfolge das Wirksamkeitsdatum ist. Bei
        // abweichender Sortierung wuerden Zeilen uebersprungen. Die Menge der an
        // einem Tag faelligen Aenderungen ist klein genug, um die IDs vorab in
        // der richtigen Reihenfolge zu holen.
        $ids = PriceChange::query()
            ->due()
            ->orderBy('effective_date')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $change = PriceChange::query()->with('customerService')->find($id);

            if (! $change) {
                continue;
            }

            ($this->applyPriceChange)($change) ? $wirksam++ : $verfallen++;
        }

        return ['applied' => $wirksam, 'lapsed' => $verfallen];
    }
}
