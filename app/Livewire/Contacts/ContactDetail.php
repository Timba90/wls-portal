<?php

namespace App\Livewire\Contacts;

use App\Actions\Contacts\ArchiveContact;
use App\Actions\Contacts\RestoreContact;
use App\Models\Contact;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detailseite eines Ansprechpartners mit allen Kundenzuordnungen.
 */
#[Layout('components.layouts.app')]
class ContactDetail extends Component
{
    public Contact $contact;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
    }

    public function archive(ArchiveContact $archiveContact): void
    {
        $archiveContact($this->contact);

        $this->contact->refresh();

        $this->dispatch('ansprechpartner-archiviert');
    }

    public function restore(RestoreContact $restoreContact): void
    {
        $restoreContact($this->contact);

        $this->contact->refresh();

        $this->dispatch('ansprechpartner-reaktiviert');
    }

    public function render(): View
    {
        $this->contact->load(['emailAddresses', 'phoneNumbers', 'assignments.customer', 'assignments.roles']);

        return view('livewire.contacts.contact-detail')->title($this->contact->fullName());
    }
}
