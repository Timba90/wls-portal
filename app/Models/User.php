<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Aktive Sitzungen dieses Benutzers.
     *
     * @return HasMany<UserSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Kunden, fuer die dieser Benutzer intern verantwortlich ist.
     *
     * @return HasMany<Customer, $this>
     */
    public function responsibleForCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'responsible_user_id');
    }

    /**
     * Ob fuer diesen Benutzer bereits ein 2FA-Geheimnis erzeugt wurde.
     *
     * Liest bewusst die rohen Attribute: direkt nach dem Anlegen eines
     * Benutzers sind die Fortify-Spalten noch nicht geladen, und der strikte
     * Model-Modus wuerde beim direkten Zugriff eine Exception werfen.
     */
    public function hasTwoFactorSecret(): bool
    {
        return ! is_null($this->getAttributes()['two_factor_secret'] ?? null);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->hasTwoFactorSecret()
            && ! is_null($this->getAttributes()['two_factor_confirmed_at'] ?? null);
    }

    /**
     * Initialen fuer die Avatar-Darstellung.
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
