<?php

namespace App\Livewire\CustomerPortal\Auth;

use App\Models\PortalUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Passwort festlegen — aus der Einladung oder nach „Passwort vergessen".
 *
 * Es gelten dieselben Passwortregeln wie fuer interne Benutzer. Nach dem
 * Festlegen ist der Kunde direkt angemeldet.
 */
#[Layout('components.layouts.guest')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ], attributes: [
            'email' => 'E-Mail-Adresse',
            'password' => 'Passwort',
        ]);

        $status = Password::broker('portal_users')->reset(
            [
                'email' => mb_strtolower(trim($this->email)),
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (PortalUser $portalUser, string $password): void {
                $portalUser->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($portalUser));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.');

            return;
        }

        $portalUser = PortalUser::query()->where('email', mb_strtolower(trim($this->email)))->firstOrFail();

        if (! $portalUser->canAccessPortal()) {
            session()->flash('status', 'Ihr Passwort wurde gespeichert, der Zugang ist derzeit aber gesperrt.');
            $this->redirectRoute('customer-portal.login');

            return;
        }

        Auth::guard('customer')->login($portalUser);
        session()->regenerate();
        $portalUser->forceFill(['last_login_at' => now()])->save();

        $this->redirectRoute('customer-portal.dashboard');
    }

    public function render(): View
    {
        return view('livewire.customer-portal.auth.reset-password')
            ->layoutData([
                'title' => 'Passwort festlegen',
                'tagline' => config('portal.customer_portal.tagline'),
                'headline' => 'Willkommen im Kundenportal.',
                'lead' => 'Legen Sie Ihr Passwort fest — danach geht es direkt los.',
            ]);
    }
}
