<?php

namespace App\Models;

use App\Notifications\CustomerPortal\SetPortalPassword;
use Database\Factories\PortalUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Zugang eines Kunden zum Kundenportal.
 *
 * Bewusst getrennt von `User`: interne Benutzer haben ausnahmslos alle Rechte,
 * ein Kundenzugang sieht ausschliesslich die Daten seines eigenen Kunden. Ein
 * eigener Guard (`customer`) haelt beide Welten auseinander.
 *
 * Zugaenge legt das Team an; der Kunde vergibt sein Passwort selbst ueber den
 * Einladungslink. Eine offene Registrierung gibt es nicht.
 */
#[Fillable(['customer_id', 'name', 'email', 'password', 'is_active', 'invited_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class PortalUser extends Authenticatable
{
    /** @use HasFactory<PortalUserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Ob der Zugang das Portal benutzen darf.
     *
     * Ein deaktivierter Zugang und ein archivierter Kunde sperren gleichermassen.
     */
    public function canAccessPortal(): bool
    {
        return $this->is_active && ! $this->customer->isArchived();
    }

    public function hasPassword(): bool
    {
        return filled($this->getAttributes()['password'] ?? null);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Link zum Setzen des Passworts — fuer Einladung und Ruecksetzung.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SetPortalPassword($token, invitation: ! $this->hasPassword()));
    }

    /**
     * @param  Builder<PortalUser>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
