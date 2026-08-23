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
     * @return int Anzahl der wirksam gewordenen Aenderungen
     */
    public function __invoke(): int
    {
        $anzahl = 0;

        PriceChange::query()
            ->due()
            ->with('customerService')
            ->orderBy('effective_date')
            ->orderBy('id')
            ->chunkById(100, function ($changes) use (&$anzahl): void {
                foreach ($changes as $change) {
                    ($this->applyPriceChange)($change);
                    $anzahl++;
                }
            });

        return $anzahl;
    }
}
