<?php

namespace App\Livewire\CustomerPortal\Auth;

use App\Models\PortalUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Anmeldung am Kundenportal.
 *
 * Unabhaengig von Fortify, das ausschliesslich den internen Guard bedient.
 * Fehlversuche werden je E-Mail-Adresse und IP begrenzt; ein gesperrter
 * Zugang bekommt dieselbe Meldung wie ein falsches Passwort, damit sich nicht
 * ausprobieren laesst, welche Adressen existieren.
 */
#[Layout('components.layouts.guest')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $schluessel = $this->throttleKey();
        $versuche = (int) config('portal.customer_portal.login_rate_limit');

        if (RateLimiter::tooManyAttempts($schluessel, $versuche)) {
            throw ValidationException::withMessages([
                'email' => 'Zu viele Anmeldeversuche. Bitte in '.RateLimiter::availableIn($schluessel).' Sekunden erneut versuchen.',
            ]);
        }

        $angemeldet = Auth::guard('customer')->attempt(
            ['email' => mb_strtolower(trim($this->email)), 'password' => $this->password],
            $this->remember,
        );

        /** @var PortalUser|null $portalUser */
        $portalUser = Auth::guard('customer')->user();

        if (! $angemeldet || ! $portalUser?->canAccessPortal()) {
            Auth::guard('customer')->logout();
            RateLimiter::hit($schluessel, 60);

            throw ValidationException::withMessages([
                'email' => 'Diese Zugangsdaten sind nicht bekannt oder der Zugang ist gesperrt.',
            ]);
        }

        RateLimiter::clear($schluessel);
        session()->regenerate();

        $portalUser->forceFill(['last_login_at' => now()])->save();

        $this->redirectIntended(route('customer-portal.dashboard'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.customer-portal.auth.login')
            ->layoutData([
                'title' => 'Anmelden',
                'tagline' => config('portal.customer_portal.tagline'),
                'headline' => 'Ihr Zugang zu Projekten, Tickets und Rechnungen.',
                'lead' => 'Rechnungen herunterladen, SEPA-Mandat erteilen, Anliegen melden und den Stand Ihrer Projekte verfolgen — an einer Stelle.',
            ]);
    }

    private function throttleKey(): string
    {
        return 'kundenportal-login:'.Str::transliterate(Str::lower($this->email)).'|'.request()->ip();
    }
}
