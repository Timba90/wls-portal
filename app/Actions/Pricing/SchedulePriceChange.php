<?php

namespace App\Actions\Pricing;

use App\Enums\PriceType;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plant eine Preisaenderung oder setzt sie sofort um.
 *
 * Preise werden niemals stillschweigend ueberschrieben — jede Aenderung
 * hinterlaesst einen Eintrag im Preisverlauf. Rueckwirkende Aenderungen sind
 * ausgeschlossen; mehrere zukuenftige Aenderungen duerfen nebeneinander
 * geplant werden.
 */
class SchedulePriceChange
{
    public function __construct(private readonly ApplyPriceChange $applyPriceChange) {}

    public function __invoke(
        CustomerService $service,
        PriceType $type,
        Money $newPrice,
        Carbon $effectiveDate,
        ?User $user = null,
        ?string $note = null,
    ): PriceChange {
        if ($service->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Kundenleistungen können nicht mehr verändert werden.'
            );
        }

        $this->guardAgainstRetroactiveChange($effectiveDate);

        return DB::transaction(function () use ($service, $type, $newPrice, $effectiveDate, $user, $note): PriceChange {
            $change = PriceChange::query()->create([
                'customer_service_id' => $service->getKey(),
                'price_type' => $type,
                'old_price_cents' => $service->{$type->column()},
                'new_price_cents' => $newPrice->cents,
                'effective_date' => $effectiveDate->toDateString(),
                'user_id' => $user?->getKey(),
                'note' => $note,
            ]);

            // Zum heutigen Datum geplante Aenderungen greifen sofort.
            if ($effectiveDate->isToday()) {
                ($this->applyPriceChange)($change);
            }

            return $change->refresh();
        });
    }

    private function guardAgainstRetroactiveChange(Carbon $effectiveDate): void
    {
        if ($effectiveDate->startOfDay()->isBefore(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'effective_date' => 'Rückwirkende Preisänderungen sind nicht möglich. Bitte wählen Sie den heutigen Tag oder ein späteres Datum.',
            ]);
        }
    }
}
