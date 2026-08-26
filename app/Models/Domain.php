<?php

namespace App\Models;

use App\Enums\RegistrarProvider;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Domain aus dem Bestand eines Registrars.
 *
 * Der Bestand wird importiert, nicht von Hand gepflegt: Registrar, Ablaufdatum
 * und Nameserver stammen aus der Schnittstelle. Von Hand gesetzt wird nur die
 * Zuordnung zu Kunde und Kundenleistung — die kennt der Registrar nicht.
 */
#[Fillable([
    'name',
    'provider',
    'provider_reference',
    'status',
    'registered_on',
    'expires_on',
    'auto_renew',
    'nameservers',
    'customer_id',
    'customer_service_id',
    'synced_at',
])]
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Die Kundenleistung, die diese Domain abrechnet.
     *
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * Domains ohne Zuordnung zu einem Kunden.
     *
     * Nach einem Import ist das der Stapel, der Arbeit macht: der Registrar
     * kennt unsere Kundennummern nicht.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereNull('customer_id');
    }

    /**
     * Domains, die innerhalb der naechsten Tage ablaufen.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('expires_on')
            ->whereDate('expires_on', '>=', now()->toDateString())
            ->whereDate('expires_on', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * Tage bis zum Ablauf; negativ, wenn bereits abgelaufen.
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expires_on instanceof CarbonInterface) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expires_on->startOfDay(), absolute: false);
    }

    public function isExpired(): bool
    {
        $tage = $this->daysUntilExpiry();

        return $tage !== null && $tage < 0;
    }

    /**
     * @return array<string, string>
     */
    public function auditLabels(): array
    {
        return [
            'name' => 'Domain',
            'provider' => 'Anbieter',
            'status' => 'Status',
            'registered_on' => 'Registriert am',
            'expires_on' => 'Läuft ab am',
            'auto_renew' => 'Automatische Verlängerung',
            'nameservers' => 'Nameserver',
            'customer_id' => 'Kunde',
            'customer_service_id' => 'Kundenleistung',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => RegistrarProvider::class,
            'registered_on' => 'date',
            'expires_on' => 'date',
            'auto_renew' => 'boolean',
            'nameservers' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
