<?php

namespace App\Models;

use App\Enums\CustomerServiceStatus;
use App\Enums\ProjectPositionKind;
use App\Models\Concerns\Auditable;
use App\Support\Money;
use Database\Factories\ProjectPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Position im Projekt: Katalogartikel, Kundenleistung oder frei erfasst.
 *
 * Der Name wird immer gespeichert, damit die Position lesbar bleibt, wenn der
 * zugrunde liegende Artikel spaeter verschwindet.
 */
#[Fillable([
    'product_id',
    'customer_service_id',
    'name',
    'kind',
    'quantity',
    'unit_price_cents',
    'status',
    'sort_order',
])]
class ProjectPosition extends Model
{
    /** @use HasFactory<ProjectPositionFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    public function isOneTime(): bool
    {
        return $this->kind === ProjectPositionKind::OneTime;
    }

    public function unitPrice(): Money
    {
        return Money::fromCents($this->unit_price_cents);
    }

    /**
     * Menge mal Einzelpreis, kaufmaennisch auf ganze Cent gerundet.
     */
    public function total(): Money
    {
        return Money::fromCents((int) round($this->unit_price_cents * (float) $this->quantity));
    }

    /**
     * Herkunft in Worten, fuer die Zusatzzeile der Positionstabelle.
     */
    public function source(): string
    {
        return match (true) {
            $this->customer_service_id !== null => 'Aus Kundenleistung',
            $this->product_id !== null => 'Aus Katalog',
            default => 'Frei erfasst',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProjectPositionKind::class,
            'status' => CustomerServiceStatus::class,
            'quantity' => 'decimal:2',
            'unit_price_cents' => 'integer',
        ];
    }
}
