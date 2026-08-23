<?php

namespace App\Models;

use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Concerns\Auditable;
use App\Support\BillingInterval;
use App\Support\Money;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Variante eines Katalogartikels, etwa Basic, Business oder Premium.
 *
 * Preise und Intervall sind optional: bleibt ein Wert leer, gilt der Wert des
 * Katalogartikels.
 */
#[Fillable([
    'name',
    'description',
    'purchase_price_cents',
    'sales_price_cents',
    'billing_interval_unit',
    'billing_interval_count',
    'sort_order',
    'status',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
        return $this->status === CatalogStatus::Archived;
    }

    /**
     * Wirksamer Einkaufspreis: eigener Wert oder der des Katalogartikels.
     */
    public function effectivePurchasePrice(): Money
    {
        return Money::fromCents(
            $this->purchase_price_cents ?? $this->product->default_purchase_price_cents,
        );
    }

    public function effectiveSalesPrice(): Money
    {
        return Money::fromCents(
            $this->sales_price_cents ?? $this->product->default_sales_price_cents,
        );
    }

    public function effectiveBillingInterval(): BillingInterval
    {
        if (is_null($this->billing_interval_unit)) {
            return $this->product->defaultBillingInterval();
        }

        return BillingInterval::make($this->billing_interval_unit, $this->billing_interval_count);
    }

    /**
     * Ob die Variante von den Werten des Katalogartikels abweicht.
     */
    public function overridesProductDefaults(): bool
    {
        return ! is_null($this->purchase_price_cents)
            || ! is_null($this->sales_price_cents)
            || ! is_null($this->billing_interval_unit);
    }

    public function effectiveMargin(): Money
    {
        return $this->effectiveSalesPrice()->minus($this->effectivePurchasePrice());
    }

    /**
     * @param  Builder<ProductVariant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CatalogStatus::Active);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogStatus::class,
            'billing_interval_unit' => BillingIntervalUnit::class,
            'purchase_price_cents' => 'integer',
            'sales_price_cents' => 'integer',
            'billing_interval_count' => 'integer',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
