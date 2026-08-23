<?php

namespace App\Models;

use App\Enums\ContactMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Zuordnung eines Ansprechpartners zu einem Firmenkunden.
 *
 * Eigenes Model statt reiner Pivot-Tabelle: Rollen, Priorität, Primaerkontakte
 * und der Aktiv-Status gelten je Zuordnung und koennen sich zwischen zwei
 * Kunden desselben Ansprechpartners unterscheiden.
 */
#[Fillable([
    'contact_id',
    'customer_id',
    'is_primary_contact',
    'is_billing_contact',
    'priority',
    'is_active',
    'preferred_contact_method',
    'primary_email_id',
    'primary_phone_id',
    'note',
])]
class ContactAssignment extends Model
{
    use Auditable;

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsToMany<ContactRole, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ContactRole::class, 'contact_assignment_role')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return BelongsTo<EmailAddress, $this>
     */
    public function primaryEmail(): BelongsTo
    {
        return $this->belongsTo(EmailAddress::class, 'primary_email_id');
    }

    /**
     * @return BelongsTo<PhoneNumber, $this>
     */
    public function primaryPhone(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'primary_phone_id');
    }

    /**
     * Fuer diese Zuordnung gueltige E-Mail-Adresse.
     *
     * Faellt auf die primaere Adresse des Ansprechpartners zurueck, wenn fuer
     * diesen Kunden keine abweichende hinterlegt ist.
     */
    public function effectiveEmail(): ?EmailAddress
    {
        return $this->primaryEmail ?? $this->contact->primaryEmailAddress();
    }

    public function effectivePhone(): ?PhoneNumber
    {
        return $this->primaryPhone ?? $this->contact->primaryPhoneNumber();
    }

    public function effectiveContactMethod(): ?ContactMethod
    {
        return $this->preferred_contact_method ?? $this->contact->preferred_contact_method;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'is_billing_contact' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'preferred_contact_method' => ContactMethod::class,
        ];
    }
}
