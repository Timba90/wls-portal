<?php

namespace App\Actions\Pricing;

use App\Models\PriceChange;
use Illuminate\Validation\ValidationException;

/**
 * Loescht eine noch nicht wirksame Preisaenderung.
 *
 * Bereits wirksam gewordene Aenderungen bleiben als Historie erhalten und
 * koennen nicht entfernt werden.
 */
class CancelPriceChange
{
    public function __invoke(PriceChange $change): void
    {
        if ($change->isApplied()) {
            throw ValidationException::withMessages([
                'price_change' => 'Bereits wirksame Preisänderungen können nicht gelöscht werden.',
            ]);
        }

        $change->delete();
    }
}
