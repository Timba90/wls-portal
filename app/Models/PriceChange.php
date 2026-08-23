<?php

namespace App\Models;

use App\Enums\PriceType;
use App\Support\Money;
use Database\Factories\PriceChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Preisaenderung im Verlauf einer Kundenleistung.
 *
 * Geplante Aenderungen tragen `applied_at = null` und werden zum
 * Wirksamkeitsdatum automatisch aktiviert.
 */
#[Fillable([
    'customer_service_id',
    'price_type',
    'old_price_cents',
    'new_price_cents',
    'effective_date',
    'applied_at',
    'user_id',
    'note',
])]
class PriceChange extends Model
{
    /** @use HasFactory<PriceChangeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApplied(): bool
    {
        return ! is_null($this->applied_at);
    }

    public function isScheduled(): bool
    {
        return is_null($this->applied_at);
    }

    public function oldPrice(): ?Money
    {
        return is_null($this->old_price_cents) ? null : Money::fromCents($this->old_price_cents);
    }

    public function newPrice(): Money
    {
        return Money::fromCents($this->new_price_cents);
    }

    /**
     * Absolute Veraenderung gegenueber dem Vorgaengerpreis.
     */
    public function difference(): ?Money
    {
        $old = $this->oldPrice();

        return $old ? $this->newPrice()->minus($old) : null;
    }

    /**
     * @param  Builder<PriceChange>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->whereNull('applied_at');
    }

    /**
     * Geplante Aenderungen, deren Wirksamkeitsdatum erreicht ist.
     *
     * @param  Builder<PriceChange>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->whereNull('applied_at')->whereDate('effective_date', '<=', now()->toDateString());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_type' => PriceType::class,
            'old_price_cents' => 'integer',
            'new_price_cents' => 'integer',
            'effective_date' => 'date',
            'applied_at' => 'datetime',
        ];
    }
}
