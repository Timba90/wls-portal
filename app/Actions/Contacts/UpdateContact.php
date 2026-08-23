<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aktualisiert Stammdaten, Kontaktkanaele und Kundenzuordnungen eines
 * Ansprechpartners.
 */
class UpdateContact
{
    public function __construct(
        private readonly SyncContactChannels $syncContactChannels,
        private readonly SyncContactAssignments $syncContactAssignments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $assignments
     * @param  array<int, array{email: string, type: string, is_primary?: bool}>  $emails
     * @param  array<int, array{number: string, type: string, is_primary?: bool}>  $phones
     */
    public function __invoke(
        Contact $contact,
        array $attributes,
        array $assignments,
        array $emails = [],
        array $phones = [],
    ): Contact {
        if ($assignments === []) {
            throw ValidationException::withMessages([
                'assignments' => 'Ein Ansprechpartner muss mindestens einem Kunden zugeordnet sein.',
            ]);
        }

        return DB::transaction(function () use ($contact, $attributes, $assignments, $emails, $phones): Contact {
            $contact->fill($attributes)->save();

            ($this->syncContactChannels)($contact, $emails, $phones);
            ($this->syncContactAssignments)($contact, $assignments);

            return $contact;
        });
    }
}
