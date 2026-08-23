<?php

namespace App\Actions\Contacts;

use App\Models\Contact;

/**
 * Archiviert einen Ansprechpartner. Keine endgueltige Loeschung.
 */
class ArchiveContact
{
    public function __invoke(Contact $contact): Contact
    {
        if (! $contact->isArchived()) {
            $contact->forceFill(['archived_at' => now()])->save();
        }

        return $contact;
    }
}
