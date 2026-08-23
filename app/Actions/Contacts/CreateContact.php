<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Legt einen Ansprechpartner an und ordnet ihn mindestens einem Kunden zu.
 *
 * Ein Ansprechpartner ohne Kundenzuordnung ist fachlich nicht vorgesehen.
 *
 * @phpstan-type AssignmentInput array{
 *     customer_id: int,
 *     role_ids?: array<int, int>,
 *     is_primary_contact?: bool,
 *     is_billing_contact?: bool,
 *     is_active?: bool,
 *     priority?: int,
 *     preferred_contact_method?: ?string,
 *     note?: ?string,
 * }
 */
class CreateContact
{
    public function __construct(
        private readonly SyncContactChannels $syncContactChannels,
        private readonly SyncContactAssignments $syncContactAssignments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, AssignmentInput>  $assignments
     * @param  array<int, array{email: string, type: string, is_primary?: bool}>  $emails
     * @param  array<int, array{number: string, type: string, is_primary?: bool}>  $phones
     */
    public function __invoke(array $attributes, array $assignments, array $emails = [], array $phones = []): Contact
    {
        if ($assignments === []) {
            throw ValidationException::withMessages([
                'assignments' => 'Ein Ansprechpartner muss mindestens einem Kunden zugeordnet sein.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $assignments, $emails, $phones): Contact {
            $contact = Contact::query()->create($attributes);

            ($this->syncContactChannels)($contact, $emails, $phones);
            ($this->syncContactAssignments)($contact, $assignments);

            return $contact;
        });
    }
}
