<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Gleicht die Kundenzuordnungen eines Ansprechpartners ab.
 *
 * Rollen, Priorität, Primaerkontakt- und Rechnungskontakt-Kennzeichen sowie der
 * Aktiv-Status gelten je Zuordnung. Mehrere Hauptansprechpartner je Kunde sind
 * ausdruecklich erlaubt.
 */
class SyncContactAssignments
{
    /**
     * @param  array<int, array<string, mixed>>  $assignments
     */
    public function __invoke(Contact $contact, array $assignments): void
    {
        DB::transaction(function () use ($contact, $assignments): void {
            $customerIds = collect($assignments)
                ->pluck('customer_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->unique();

            $contact->assignments()
                ->whereNotIn('customer_id', $customerIds)
                ->get()
                ->each(fn (ContactAssignment $assignment) => $assignment->delete());

            foreach ($assignments as $assignment) {
                if (blank($assignment['customer_id'] ?? null)) {
                    continue;
                }

                /** @var ContactAssignment $record */
                $record = $contact->assignments()->updateOrCreate(
                    ['customer_id' => (int) $assignment['customer_id']],
                    [
                        'is_primary_contact' => (bool) ($assignment['is_primary_contact'] ?? false),
                        'is_billing_contact' => (bool) ($assignment['is_billing_contact'] ?? false),
                        'is_active' => (bool) ($assignment['is_active'] ?? true),
                        'priority' => (int) ($assignment['priority'] ?? 100),
                        'preferred_contact_method' => $assignment['preferred_contact_method'] ?? null,
                        'primary_email_id' => $assignment['primary_email_id'] ?? null,
                        'primary_phone_id' => $assignment['primary_phone_id'] ?? null,
                        'note' => $assignment['note'] ?? null,
                    ],
                );

                $record->roles()->sync($assignment['role_ids'] ?? []);
            }

            $contact->unsetRelation('assignments');
        });
    }
}
