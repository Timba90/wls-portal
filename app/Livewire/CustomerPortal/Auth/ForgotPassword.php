<?php

namespace App\Livewire\CustomerPortal\Auth;

use App\Models\PortalUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Ruecksetzlink anfordern.
 *
 * Die Antwort ist immer dieselbe — ob die Adresse existiert, verraet die
 * Seite nicht. Gesperrte Zugaenge bekommen keinen Link.
 */
#[Layout('components.layouts.guest')]
class ForgotPassword extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    public bool $sent = false;

    public function sendLink(): void
    {
        $this->validate();

        $schluessel = 'kundenportal-passwort:'.request()->ip();

        if (! RateLimiter::attempt($schluessel, 5, fn (): bool => true, 600)) {
            $this->addError('email', 'Zu viele Anfragen. Bitte später erneut versuchen.');

            return;
        }

        $portalUser = PortalUser::query()
            ->where('email', mb_strtolower(trim($this->email)))
            ->first();

        if ($portalUser?->canAccessPortal()) {
            Password::broker('portal_users')->sendResetLink(['email' => $portalUser->email]);
        }

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.customer-portal.auth.forgot-password')
            ->layoutData([
                'title' => 'Passwort vergessen',
                'tagline' => config('portal.customer_portal.tagline'),
                'headline' => 'Neues Passwort in zwei Schritten.',
                'lead' => 'Wir schicken Ihnen einen Link, mit dem Sie ein neues Passwort festlegen.',
            ]);
    }
}
