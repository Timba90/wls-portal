<?php

namespace App\Models;

use App\Enums\BillingIntervalUnit;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\Concerns\HasTags;
use App\Support\BillingInterval;
use App\Support\Money;
use Database\Factories\CustomerServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Die bei einem Kunden tatsaechlich bestehende Leistung.
 *
 * Kann auf einem Katalogartikel beruhen — dann bleibt die Herkunft erhalten —
 * oder vollstaendig individuell sein. Archivierte Leistungen sind
 * schreibgeschuetzt; das wird im Model erzwungen, nicht nur in der Oberflaeche.
 */
#[Fillable([
    'customer_id',
    'product_id',
    'product_variant_id',
    'catalog_snapshot',
    'name',
    'billing_label',
    'description',
    'status',
    'purchase_price_cents',
    'sales_price_cents',
    'billing_interval_unit',
    'billing_interval_count',
    'service_start_date',
    'billing_start_date',
    'first_billing_date',
    'category_id',
    'subcategory_id',
    'responsible_user_id',
])]
class CustomerService extends Model
{
    /** @use HasFactory<CustomerServiceFactory> */
    use HasFactory, HasTags;

    protected static function booted(): void
    {
        // Archivierte Leistungen bleiben historisch unveraendert. Erlaubt bleibt
        // ausschliesslich das Aufheben der Archivierung selbst.
        static::updating(function (self $service): void {
            // getRawOriginal statt getOriginal: getOriginal wendet den
            // Enum-Cast an und liefert kein vergleichbares Rohformat.
            $warArchiviert = $service->getRawOriginal('status') === CustomerServiceStatus::Archived->value;
            $wirdReaktiviert = $service->isDirty('status')
                && $service->status !== CustomerServiceStatus::Archived;

            if ($warArchiviert && ! $wirdReaktiviert) {
                throw new ReadOnlyRecordException(
                    'Archivierte Kundenleistungen können nicht mehr verändert werden.'
                );
            }
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Vollstaendiger Preisverlauf inklusive geplanter Aenderungen.
     *
     * @return HasMany<PriceChange, $this>
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class);
    }

    /**
     * @return MorphMany<ServiceComponent, $this>
     */
    public function serviceComponents(): MorphMany
    {
        return $this->morphMany(ServiceComponent::class, 'componentable')->orderBy('sort_order');
    }

    public function isArchived(): bool
    {
        return $this->status === CustomerServiceStatus::Archived;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Ob die Leistung auf einem Katalogartikel beruht.
     */
    public function isFromCatalog(): bool
    {
        return ! is_null($this->product_id);
    }

    public function purchasePrice(): Money
    {
        return Money::fromCents($this->purchase_price_cents);
    }

    public function salesPrice(): Money
    {
        return Money::fromCents($this->sales_price_cents);
    }

    public function billingInterval(): BillingInterval
    {
        return BillingInterval::make($this->billing_interval_unit, $this->billing_interval_count);
    }

    /**
     * Absolute Marge, zugleich Deckungsbeitrag der Leistung.
     */
    public function margin(): Money
    {
        return $this->salesPrice()->minus($this->purchasePrice());
    }

    /**
     * Marge in Prozent des Verkaufspreises; `null` ohne Verkaufspreis.
     */
    public function marginPercentage(): ?float
    {
        if ($this->sales_price_cents === 0) {
            return null;
        }

        return round($this->margin()->cents / $this->sales_price_cents * 100, 2);
    }

    /**
     * Auf einen Monat normalisierter Verkaufspreis.
     *
     * Einmalige Leistungen ergeben 0 — sie wiederholen sich nicht.
     */
    public function monthlyRevenue(): Money
    {
        return $this->billingInterval()->toMonthly($this->salesPrice());
    }

    public function yearlyRevenue(): Money
    {
        return $this->billingInterval()->toYearly($this->salesPrice());
    }

    public function monthlyCosts(): Money
    {
        return $this->billingInterval()->toMonthly($this->purchasePrice());
    }

    public function yearlyCosts(): Money
    {
        return $this->billingInterval()->toYearly($this->purchasePrice());
    }

    public function monthlyMargin(): Money
    {
        return $this->monthlyRevenue()->minus($this->monthlyCosts());
    }

    /**
     * Ob die Leistung in Umsatz- und Margenkennzahlen einfliesst.
     *
     * Bewusst nicht abgerechnete Leistungen werden separat ausgewiesen.
     */
    public function countsTowardsRevenue(): bool
    {
        return $this->status->countsTowardsRevenue()
            && ! $this->do_not_bill
            && $this->billingInterval()->isRecurring();
    }

    /**
     * Abweichungen gegenueber den Katalogwerten zum Verknuepfungszeitpunkt.
     *
     * @return array<string, array{katalog: string, kunde: string}>
     */
    public function catalogDeviations(): array
    {
        $snapshot = $this->catalog_snapshot;

        if (blank($snapshot)) {
            return [];
        }

        $deviations = [];

        if (isset($snapshot['purchase_price_cents']) && $snapshot['purchase_price_cents'] !== $this->purchase_price_cents) {
            $deviations['Einkaufspreis'] = [
                'katalog' => Money::fromCents($snapshot['purchase_price_cents'])->format(),
                'kunde' => $this->purchasePrice()->format(),
            ];
        }

        if (isset($snapshot['sales_price_cents']) && $snapshot['sales_price_cents'] !== $this->sales_price_cents) {
            $deviations['Verkaufspreis'] = [
                'katalog' => Money::fromCents($snapshot['sales_price_cents'])->format(),
                'kunde' => $this->salesPrice()->format(),
            ];
        }

        $katalogIntervall = isset($snapshot['billing_interval_unit'])
            ? BillingInterval::make(
                BillingIntervalUnit::from($snapshot['billing_interval_unit']),
                $snapshot['billing_interval_count'] ?? null,
            )
            : null;

        if ($katalogIntervall && $katalogIntervall->label() !== $this->billingInterval()->label()) {
            $deviations['Abrechnungsintervall'] = [
                'katalog' => $katalogIntervall->label(),
                'kunde' => $this->billingInterval()->label(),
            ];
        }

        return $deviations;
    }

    /**
     * @param  Builder<CustomerService>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerServiceStatus::Active);
    }

    /**
     * @param  Builder<CustomerService>  $query
     */
    public function scopeBillable(Builder $query): void
    {
        $query->where('status', CustomerServiceStatus::Active)
            ->where('do_not_bill', false)
            ->where('billing_interval_unit', '!=', BillingIntervalUnit::Once);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerServiceStatus::class,
            'billing_interval_unit' => BillingIntervalUnit::class,
            'do_not_bill_reason' => DoNotBillReason::class,
            'catalog_snapshot' => 'array',
            'purchase_price_cents' => 'integer',
            'sales_price_cents' => 'integer',
            'billing_interval_count' => 'integer',
            'do_not_bill' => 'boolean',
            'service_start_date' => 'date',
            'billing_start_date' => 'date',
            'first_billing_date' => 'date',
            'do_not_bill_since' => 'datetime',
            'do_not_bill_released_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
