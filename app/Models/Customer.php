<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Exceptions\ImmutableAttributeException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasTags;
use App\Support\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

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
    use Auditable, HasCustomFields, HasDocuments, HasFactory, HasNotes, HasTags;

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
     * @return HasMany<CustomerService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(CustomerService::class);
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
     * Initialen fuer die Avatar-Kachel.
     *
     * Bei Firmenkunden die ersten beiden Buchstaben des Firmennamens, bei
     * Privatkunden je der erste Buchstabe von Vor- und Nachname.
     */
    public function initials(): string
    {
        if ($this->isPrivate() && filled($this->first_name) && filled($this->last_name)) {
            return Str::upper(Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1));
        }

        return Str::upper(Str::substr(trim($this->displayName()), 0, 2));
    }

    /**
     * Primaere E-Mail-Adresse, sofern hinterlegt.
     */
    public function primaryEmailAddress(): ?EmailAddress
    {
        return $this->emailAddresses->firstWhere('is_primary', true);
    }

    /**
     * Name des Hauptansprechpartners fuer die Listendarstellung.
     *
     * Bei Firmenkunden der als Hauptansprechpartner markierte Kontakt, bei
     * Privatkunden die Person selbst. `null`, wenn keiner hinterlegt ist.
     */
    public function primaryContactName(): ?string
    {
        if ($this->isPrivate()) {
            return $this->displayName();
        }

        return $this->primaryAssignment()?->contact->fullName();
    }

    /**
     * E-Mail-Adresse, unter der dieser Kunde regulaer erreichbar ist.
     */
    public function primaryContactEmail(): ?string
    {
        if ($this->isPrivate()) {
            return $this->primaryEmailAddress()?->email;
        }

        return $this->primaryAssignment()?->effectiveEmail()?->email;
    }

    /**
     * Erste als Hauptansprechpartner markierte Zuordnung.
     *
     * Mehrere Hauptansprechpartner sind zulaessig — fuer die Liste zaehlt der
     * mit der hoechsten Prioritaet, also der erste der bereits sortierten
     * Beziehung.
     */
    private function primaryAssignment(): ?ContactAssignment
    {
        return $this->contactAssignments->firstWhere('is_primary_contact', true);
    }

    public function primaryPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumbers->firstWhere('is_primary', true);
    }

    /**
     * Auf einen Monat normalisierter Umsatz aller abrechnungsrelevanten
     * Leistungen.
     *
     * Erwartet die Beziehung `services` als geladenen Bestand; die Kundenliste
     * laedt sie mit dem Scope `billable` vor.
     */
    public function monthlyRevenue(): Money
    {
        return $this->sumOverBillableServices(
            fn (CustomerService $service): Money => $service->monthlyRevenue(),
        );
    }

    public function yearlyRevenue(): Money
    {
        return $this->sumOverBillableServices(
            fn (CustomerService $service): Money => $service->yearlyRevenue(),
        );
    }

    public function monthlyCosts(): Money
    {
        return $this->sumOverBillableServices(
            fn (CustomerService $service): Money => $service->monthlyCosts(),
        );
    }

    public function monthlyMargin(): Money
    {
        return $this->monthlyRevenue()->minus($this->monthlyCosts());
    }

    /**
     * @param  callable(CustomerService): Money  $value
     */
    private function sumOverBillableServices(callable $value): Money
    {
        return $this->services
            ->filter(fn (CustomerService $service): bool => $service->countsTowardsRevenue())
            ->reduce(
                fn (Money $carry, CustomerService $service): Money => $carry->plus($value($service)),
                Money::zero(),
            );
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
    public function auditLabels(): array
    {
        return [
            'customer_number' => 'Kundennummer',
            'type' => 'Kundentyp',
            'company_name' => 'Firmenname',
            'salutation' => 'Anrede',
            'academic_title' => 'Akademischer Titel',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'birth_date' => 'Geburtsdatum',
            'gender' => 'Geschlecht',
            'short_label' => 'Kurzbezeichnung',
            'internal_code' => 'Internes Kürzel',
            'status' => 'Status',
            'responsible_user_id' => 'Interner Verantwortlicher',
            'archived_at' => 'Archiviert am',
        ];
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
