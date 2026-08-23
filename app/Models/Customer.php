<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Exceptions\ImmutableAttributeException;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Firmen- oder Privatkunde.
 *
 * Die Kundennummer wird beim Anlegen vergeben und ist danach unveraenderlich.
 */
#[Fillable([
    'type',
    'company_name',
    'salutation',
    'academic_title',
    'first_name',
    'last_name',
    'birth_date',
    'gender',
    'short_label',
    'internal_code',
    'status',
    'responsible_user_id',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Die Kundennummer darf nach der Erstellung nicht mehr geaendert werden.
        // Serverseitig erzwungen, nicht nur in der Oberflaeche.
        static::updating(function (self $customer): void {
            if ($customer->isDirty('customer_number')) {
                throw new ImmutableAttributeException(
                    'Die Kundennummer kann nach der Erstellung nicht mehr geändert werden.'
                );
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Zuordnungen von Ansprechpartnern — nur bei Firmenkunden.
     *
     * @return HasMany<ContactAssignment, $this>
     */
    public function contactAssignments(): HasMany
    {
        return $this->hasMany(ContactAssignment::class)->orderBy('priority');
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_assignments');
    }

    /**
     * @return HasMany<ContactDeputy, $this>
     */
    public function contactDeputies(): HasMany
    {
        return $this->hasMany(ContactDeputy::class)->orderBy('priority');
    }

    /**
     * @return MorphMany<EmailAddress, $this>
     */
    public function emailAddresses(): MorphMany
    {
        return $this->morphMany(EmailAddress::class, 'owner')->orderBy('sort_order');
    }

    /**
     * @return MorphMany<PhoneNumber, $this>
     */
    public function phoneNumbers(): MorphMany
    {
        return $this->morphMany(PhoneNumber::class, 'owner')->orderBy('sort_order');
    }

    public function isCompany(): bool
    {
        return $this->type === CustomerType::Company;
    }

    public function isPrivate(): bool
    {
        return $this->type === CustomerType::Private;
    }

    public function isArchived(): bool
    {
        return $this->status === CustomerStatus::Archived;
    }

    /**
     * Anzeigename: Firmenname beziehungsweise vollstaendiger Personenname.
     */
    public function displayName(): string
    {
        if ($this->isCompany()) {
            return (string) $this->company_name;
        }

        return collect([$this->academic_title, $this->first_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    /**
     * Primaere E-Mail-Adresse, sofern hinterlegt.
     */
    public function primaryEmailAddress(): ?EmailAddress
    {
        return $this->emailAddresses->firstWhere('is_primary', true);
    }

    public function primaryPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumbers->firstWhere('is_primary', true);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerStatus::Active);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->where('status', CustomerStatus::Archived);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'status' => CustomerStatus::class,
            'gender' => Gender::class,
            'salutation' => Salutation::class,
            'birth_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
