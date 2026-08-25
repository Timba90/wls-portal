<?php

namespace App\Actions\Services;

use App\Actions\Pricing\SchedulePriceChange;
use App\Enums\BillingIntervalUnit;
use App\Enums\PriceType;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\CustomerService;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Entscheidet eine Katalogaenderung fuer ein einzelnes Feld.
 *
 * Zwei Ausgaenge, beide abschliessend:
 *
 * - **uebernehmen** — die Leistung bekommt den heutigen Katalogwert.
 * - **behalten** — die Leistung bleibt, wie sie ist; die Aenderung gilt als
 *   gesehen und taucht nicht wieder auf.
 *
 * In beiden Faellen wandert der heutige Katalogwert in den gesehenen Stand.
 * Nur fuer dieses eine Feld: andere offene Aenderungen duerfen nicht
 * nebenbei verschwinden, nur weil jemand ueber den Preis entschieden hat.
 *
 * Preise laufen ueber den Preisverlauf, nicht ueber einen direkten Schreibzugriff
 * — eine uebernommene Katalogerhoehung ist eine Preisaenderung wie jede andere
 * und gehoert in die Historie.
 */
class ResolveCatalogChange
{
    public function __construct(
        private readonly BuildCatalogSnapshot $buildCatalogSnapshot,
        private readonly SchedulePriceChange $schedulePriceChange,
    ) {}

    public function __invoke(
        CustomerService $service,
        string $field,
        bool $adopt,
        ?User $user = null,
    ): CustomerService {
        if ($service->isArchived()) {
            throw new ReadOnlyRecordException(
                'Archivierte Kundenleistungen können nicht mehr verändert werden.'
            );
        }

        $heute = ($this->buildCatalogSnapshot)($service->product, $service->productVariant);

        if (blank($heute)) {
            throw new InvalidArgumentException(
                'Diese Leistung beruht auf keinem Katalogartikel.'
            );
        }

        return DB::transaction(function () use ($service, $field, $adopt, $user, $heute): CustomerService {
            if ($adopt) {
                $this->adopt($service, $field, $heute, $user);
            }

            $this->markAsSeen($service, $field, $heute);

            return $service->refresh();
        });
    }

    /**
     * Uebernimmt alle offenen Aenderungen auf einmal.
     *
     * @param  array<int, string>  $fields
     */
    public function all(CustomerService $service, array $fields, bool $adopt, ?User $user = null): CustomerService
    {
        foreach ($fields as $feld) {
            $this($service, $feld, $adopt, $user);
        }

        return $service->refresh();
    }

    /**
     * @param  array<string, mixed>  $heute
     */
    private function adopt(CustomerService $service, string $field, array $heute, ?User $user): void
    {
        match ($field) {
            'sales_price_cents' => $this->adoptPrice($service, PriceType::Sales, $heute, $user),
            'purchase_price_cents' => $this->adoptPrice($service, PriceType::Purchase, $heute, $user),
            'billing_interval' => $this->adoptInterval($service, $heute),
            'category' => $this->adoptCategory($service, $heute),
            default => throw new InvalidArgumentException(
                "Das Feld „{$field}\" lässt sich nicht aus dem Katalog übernehmen."
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $heute
     */
    private function adoptPrice(CustomerService $service, PriceType $type, array $heute, ?User $user): void
    {
        $schluessel = $type->column();
        $neu = (int) ($heute[$schluessel] ?? 0);

        if ($neu === $service->{$schluessel}) {
            return;
        }

        ($this->schedulePriceChange)(
            $service,
            $type,
            Money::fromCents($neu),
            now(),
            $user,
            'Aus dem Katalog übernommen.',
        );
    }

    /**
     * @param  array<string, mixed>  $heute
     */
    private function adoptInterval(CustomerService $service, array $heute): void
    {
        $einheit = BillingIntervalUnit::from($heute['billing_interval_unit']);

        $service->forceFill([
            'billing_interval_unit' => $einheit,
            'billing_interval_count' => $einheit->requiresCount()
                ? max(1, (int) ($heute['billing_interval_count'] ?? 1))
                : null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $heute
     */
    private function adoptCategory(CustomerService $service, array $heute): void
    {
        $service->forceFill([
            'category_id' => $heute['category_id'] ?? null,
            'subcategory_id' => $heute['subcategory_id'] ?? null,
        ])->save();
    }

    /**
     * Schreibt den heutigen Katalogwert dieses einen Feldes in den gesehenen
     * Stand.
     *
     * @param  array<string, mixed>  $heute
     */
    private function markAsSeen(CustomerService $service, string $field, array $heute): void
    {
        $stand = $service->catalogBaseline() ?? [];

        foreach ($this->keysOf($field) as $schluessel) {
            $stand[$schluessel] = $heute[$schluessel] ?? null;
        }

        $stand['erfasst_am'] = $heute['erfasst_am'];

        $service->forceFill([
            'catalog_reviewed_snapshot' => $stand,
            'catalog_reviewed_at' => now(),
        ])->save();
    }

    /**
     * Die Snapshot-Schluessel hinter einem Vergleichsfeld.
     *
     * @return array<int, string>
     */
    private function keysOf(string $field): array
    {
        return match ($field) {
            'sales_price_cents', 'purchase_price_cents' => [$field],
            'billing_interval' => ['billing_interval_unit', 'billing_interval_count'],
            'category' => ['category_id', 'subcategory_id'],
            'product_name' => ['product_name', 'product_variant_name'],
            default => throw new InvalidArgumentException("Unbekanntes Vergleichsfeld „{$field}\"."),
        };
    }
}
