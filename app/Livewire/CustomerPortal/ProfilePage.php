<?php

namespace App\Livewire\CustomerPortal;

use App\Livewire\Concerns\WithPortalCustomer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Eigener Name und Passwort des Kundenzugangs.
 *
 * Die E-Mail-Adresse aendert das Team — sie ist zugleich die Kennung, unter
 * der Einladungen und Benachrichtigungen ankommen.
 */
#[Layout('components.layouts.customer')]
class ProfilePage extends Component
{
    use WithPortalCustomer;

    public string $name = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = $this->portalUser()->name;
    }

    public function saveProfile(): void
    {
        $validated = $this->validate(['name' => ['required', 'string', 'max:255']], attributes: ['name' => 'Name']);

        $this->portalUser()->update(['name' => trim($validated['name'])]);

        $this->dispatch('profil-gespeichert');
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password:customer'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], attributes: [
            'currentPassword' => 'Aktuelles Passwort',
            'password' => 'Neues Passwort',
        ]);

        $portalUser = $this->portalUser();
        $portalUser->update(['password' => $this->password]);

        // Andere Sitzungen dieses Zugangs abmelden.
        Auth::guard('customer')->logoutOtherDevices($this->password);

        $this->reset('currentPassword', 'password', 'password_confirmation');
        $this->dispatch('passwort-geaendert');
    }

    public function render(): View
    {
        return view('livewire.customer-portal.profile-page', [
            'portalUser' => $this->portalUser(),
            'customer' => $this->customer(),
        ])->title('Profil');
    }
}
