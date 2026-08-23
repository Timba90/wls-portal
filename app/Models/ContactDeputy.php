<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertretung fuer eine Rolle bei einem bestimmten Kunden.
 *
 * Je Kunde und Rolle koennen mehrere Vertretungen mit Priorität hinterlegt
 * werden; die niedrigste Priorität wird zuerst herangezogen.
 */
#[Fillable(['customer_id', 'contact_role_id', 'contact_id', 'priority'])]
class ContactDeputy extends Model
{
    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<ContactRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(ContactRole::class, 'contact_role_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }
}
