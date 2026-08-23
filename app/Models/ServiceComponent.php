<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\ServiceComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Strukturierter Leistungsbestandteil.
 *
 * Wird von Katalogartikeln, Artikelvarianten und Kundenleistungen gleichermassen
 * verwendet.
 */
#[Fillable(['title', 'description', 'sort_order', 'purchase_price_cents', 'sales_price_cents'])]
class ServiceComponent extends Model
{
    /** @use HasFactory<ServiceComponentFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function componentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function purchasePrice(): ?Money
    {
        return is_null($this->purchase_price_cents)
            ? null
            : Money::fromCents($this->purchase_price_cents);
    }

    public function salesPrice(): ?Money
    {
        return is_null($this->sales_price_cents)
            ? null
            : Money::fromCents($this->sales_price_cents);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'purchase_price_cents' => 'integer',
            'sales_price_cents' => 'integer',
        ];
    }
}
