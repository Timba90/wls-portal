<?php

namespace App\Models;

use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Concerns\HasTags;
use App\Support\BillingInterval;
use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Katalogartikel — in der Oberflaeche „Artikel / Leistung".
 *
 * Traegt die Standardwerte, von denen eine Kundenleistung abweichen darf.
 */
#[Fillable([
    'name',
    'internal_name',
    'description',
    'category_id',
    'subcategory_id',
    'status',
    'default_purchase_price_cents',
    'default_sales_price_cents',
    'default_billing_interval_unit',
    'default_billing_interval_count',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTags;

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
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('name');
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

    public function defaultPurchasePrice(): Money
    {
        return Money::fromCents($this->default_purchase_price_cents);
    }

    public function defaultSalesPrice(): Money
    {
        return Money::fromCents($this->default_sales_price_cents);
    }

    public function defaultBillingInterval(): BillingInterval
    {
        return BillingInterval::make(
            $this->default_billing_interval_unit,
            $this->default_billing_interval_count,
        );
    }

    /**
     * Absolute Marge des Standardpreises.
     */
    public function defaultMargin(): Money
    {
        return $this->defaultSalesPrice()->minus($this->defaultPurchasePrice());
    }

    /**
     * Marge in Prozent des Verkaufspreises; `null`, wenn kein Verkaufspreis
     * hinterlegt ist.
     */
    public function defaultMarginPercentage(): ?float
    {
        if ($this->default_sales_price_cents === 0) {
            return null;
        }

        return round($this->defaultMargin()->cents / $this->default_sales_price_cents * 100, 2);
    }

    /**
     * @param  Builder<Product>  $query
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
            'default_billing_interval_unit' => BillingIntervalUnit::class,
            'default_purchase_price_cents' => 'integer',
            'default_sales_price_cents' => 'integer',
            'default_billing_interval_count' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
