<?php

namespace App\Livewire\Customers;

use App\Actions\CustomerPortal\InvitePortalUser;
use App\Actions\CustomerPortal\LinkFluxContact;
use App\Actions\CustomerPortal\SetPortalUserActive;
use App\Models\Customer;
use App\Models\PortalUser;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Reiter „Kundenportal" im Kundendetail.
 *
 * Hier verknuepft das Team den Kunden mit seinem Flux-Kontakt (Grundlage fuer
 * Rechnungen und SEPA-Mandat) und verwaltet die Zugaenge des Kunden.
 */
class CustomerPortalAccess extends Component
{
    public Customer $customer;

    public string $fluxContactId = '';

    public bool $showInviteForm = false;

    public string $inviteName = '';

    public string $inviteEmail = '';

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
        $this->fluxContactId = (string) ($customer->flux_contact_id ?? '');
    }

    public function saveFluxContact(LinkFluxContact $linkFluxContact): void
    {
        $linkFluxContact($this->customer, $this->fluxContactId);

        $this->customer->refresh();
        $this->dispatch('flux-kontakt-gespeichert');
    }

    public function invite(InvitePortalUser $invitePortalUser): void
    {
        $invitePortalUser($this->customer, ['name' => $this->inviteName, 'email' => $this->inviteEmail]);

        $this->reset('inviteName', 'inviteEmail', 'showInviteForm');
        $this->dispatch('zugang-eingeladen');
    }

    public function resendInvitation(int $portalUserId, InvitePortalUser $invitePortalUser): void
    {
        $invitePortalUser->sendInvitation($this->portalUser($portalUserId));

        $this->dispatch('einladung-versendet');
    }

    public function toggleActive(int $portalUserId, SetPortalUserActive $setPortalUserActive): void
    {
        $portalUser = $this->portalUser($portalUserId);

        $setPortalUserActive($portalUser, ! $portalUser->is_active);
    }

    public function render(): View
    {
        return view('livewire.customers.customer-portal-access', [
            'portalUsers' => $this->customer->portalUsers()->get(),
            'portalUrl' => route('customer-portal.login'),
        ]);
    }

    private function portalUser(int $portalUserId): PortalUser
    {
        return $this->customer->portalUsers()->findOrFail($portalUserId);
    }
}
