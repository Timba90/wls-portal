<?php

namespace App\Models;

use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasTags;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Ansprechpartner eines oder mehrerer Firmenkunden.
 *
 * Traegt bewusst kein Firmenfeld: die Verbindung entsteht ausschliesslich ueber
 * ContactAssignment, damit derselbe Ansprechpartner bei mehreren Kunden mit
 * unterschiedlichen Rollen gefuehrt werden kann.
 */
#[Fillable([
    'salutation',
    'academic_title',
    'first_name',
    'last_name',
    'gender',
    'birth_date',
    'preferred_contact_method',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use Auditable, HasDocuments, HasFactory, HasNotes, HasTags;

    /**
     * @return HasMany<ContactAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ContactAssignment::class)->orderBy('priority');
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'contact_assignments');
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

    /**
     * @return HasMany<ContactDeputy, $this>
     */
    public function deputyFor(): HasMany
    {
        return $this->hasMany(ContactDeputy::class);
    }

    public function isArchived(): bool
    {
        return ! is_null($this->archived_at);
    }

    public function fullName(): string
    {
        return collect([$this->academic_title, $this->first_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    /**
     * Name in Listenform: "Nachname, Vorname".
     */
    public function listName(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    public function primaryEmailAddress(): ?EmailAddress
    {
        return $this->emailAddresses->firstWhere('is_primary', true);
    }

    public function primaryPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumbers->firstWhere('is_primary', true);
    }

    /**
     * @param  Builder<Contact>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @return array<string, string>
     */
    public function auditLabels(): array
    {
        return [
            'salutation' => 'Anrede',
            'academic_title' => 'Akademischer Titel',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'gender' => 'Geschlecht',
            'birth_date' => 'Geburtsdatum',
            'preferred_contact_method' => 'Bevorzugte Kontaktart',
            'archived_at' => 'Archiviert am',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salutation' => Salutation::class,
            'gender' => Gender::class,
            'preferred_contact_method' => ContactMethod::class,
            'birth_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
