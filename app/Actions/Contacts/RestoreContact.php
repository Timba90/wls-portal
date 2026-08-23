<?php

namespace App\Actions\Contacts;

use App\Models\Contact;

/**
 * Hebt die Archivierung eines Ansprechpartners auf.
 */
class RestoreContact
{
    public function __invoke(Contact $contact): Contact
    {
        if ($contact->isArchived()) {
            $contact->forceFill(['archived_at' => null])->save();
        }

        return $contact;
    }
}
