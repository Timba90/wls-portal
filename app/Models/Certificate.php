<?php

namespace App\Models;

use App\Enums\RegistrarProvider;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein SSL-Zertifikat aus dem Bestand eines Anbieters.
 *
 * Wie bei den Domains: der technische Stand kommt aus der Schnittstelle, die
 * Zuordnung zu Kunde und Leistung von Hand.
 */
#[Fillable([
    'common_name',
    'provider',
    'provider_reference',
    'status',
    'issuer',
    'issued_on',
    'expires_on',
    'alternative_names',
    'customer_id',
    'customer_service_id',
    'synced_at',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * @param  Builder<Certificate>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereNull('customer_id');
    }

    /**
     * Einem Kunden zugeordnet, aber ohne Kundenleistung.
     *
     * Die interessante Luecke nach dem Zuordnen: der Kunde steht fest, die
     * Verbindung zur Abrechnung fehlt noch.
     *
     * @param  Builder<Certificate>  $query
     */
    public function scopeWithoutService(Builder $query): void
    {
        $query->whereNotNull('customer_id')->whereNull('customer_service_id');
    }

    /**
     * @param  Builder<Certificate>  $query
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
            'common_name' => 'Hauptname',
            'provider' => 'Anbieter',
            'status' => 'Status',
            'issuer' => 'Aussteller',
            'issued_on' => 'Ausgestellt am',
            'expires_on' => 'Läuft ab am',
            'alternative_names' => 'Weitere Namen',
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
            'issued_on' => 'date',
            'expires_on' => 'date',
            'alternative_names' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
