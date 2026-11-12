<?php

namespace App\Models;

use App\Enums\LexofficeExecutionInterval;
use App\Support\Money;
use Database\Factories\LexofficeRecurringTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine aus Lexoffice gelesene Serienrechnungsvorlage.
 *
 * Lexoffice bleibt die Quelle: der Abgleich ueberschreibt alle Felder ausser
 * der Kundenzuordnung. Bewusst nicht auditierbar — die Daten aendert kein
 * Mensch im Portal.
 */
#[Fillable([
    'lexoffice_id',
    'lexoffice_contact_id',
    'contact_name',
    'customer_id',
    'title',
    'execution_interval',
    'execution_status',
    'start_date',
    'end_date',
    'next_execution_date',
    'last_execution_date',
    'last_execution_failed',
    'tax_type',
    'total_net_cents',
    'total_gross_cents',
    'lexoffice_updated_at',
    'synced_at',
])]
class LexofficeRecurringTemplate extends Model
{
    /** @use HasFactory<LexofficeRecurringTemplateFactory> */
    use HasFactory;

    public const string StatusActive = 'ACTIVE';

    public const string StatusPaused = 'PAUSED';

    public const string StatusEnded = 'ENDED';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<LexofficeRecurringTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LexofficeRecurringTemplateItem::class)->orderBy('position');
    }

    public function isActive(): bool
    {
        return $this->execution_status === self::StatusActive;
    }

    public function statusLabel(): string
    {
        return match ($this->execution_status) {
            self::StatusActive => 'Aktiv',
            self::StatusPaused => 'Pausiert',
            self::StatusEnded => 'Beendet',
            default => (string) $this->execution_status,
        };
    }

    public function totalNet(): Money
    {
        return Money::fromCents($this->total_net_cents);
    }

    /**
     * Nur aktive Vorlagen erzeugen Rechnungen und zaehlen als Abrechnung.
     *
     * @param  Builder<LexofficeRecurringTemplate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('execution_status', self::StatusActive);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'execution_interval' => LexofficeExecutionInterval::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_execution_date' => 'date',
            'last_execution_date' => 'date',
            'last_execution_failed' => 'boolean',
            'total_net_cents' => 'integer',
            'total_gross_cents' => 'integer',
            'lexoffice_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
