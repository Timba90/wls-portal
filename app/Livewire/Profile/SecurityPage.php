<?php

namespace App\Livewire\Profile;

use App\Actions\Auth\TerminateSession;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Sicherheitseinstellungen: Zwei-Faktor-Authentifizierung und Sitzungen.
 */
#[Layout('components.layouts.app')]
#[Title('Sicherheit')]
class SecurityPage extends Component
{
    public bool $showingQrCode = false;

    public bool $showingRecoveryCodes = false;

    public string $code = '';

    public function enableTwoFactor(EnableTwoFactorAuthentication $enable): void
    {
        $enable($this->user());

        $this->showingQrCode = true;
        $this->showingRecoveryCodes = false;
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(
            ['code' => ['required', 'string']],
            attributes: ['code' => 'Code'],
        );

        $confirm($this->user(), $this->code);

        $this->reset('code');
        $this->showingQrCode = false;
        $this->showingRecoveryCodes = true;

        $this->dispatch('zwei-faktor-aktiviert');
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $disable($this->user());

        $this->showingQrCode = false;
        $this->showingRecoveryCodes = false;

        $this->dispatch('zwei-faktor-deaktiviert');
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate($this->user());

        $this->showingRecoveryCodes = true;

        $this->dispatch('wiederherstellungscodes-erneuert');
    }

    public function showRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = true;
    }

    public function terminateSession(string $sessionId, TerminateSession $terminate): void
    {
        $terminate($this->user(), $sessionId);

        $this->dispatch('sitzung-beendet');
    }

    public function terminateOtherSessions(TerminateSession $terminate): void
    {
        $terminate->allExceptCurrent($this->user());

        $this->dispatch('sitzungen-beendet');
    }

    public function render(): View
    {
        return view('livewire.profile.security-page', [
            'sessions' => $this->sessions(),
            'recoveryCodes' => $this->recoveryCodes(),
        ]);
    }

    /**
     * @return Collection<int, UserSession>
     */
    private function sessions(): Collection
    {
        return $this->user()
            ->sessions()
            ->orderByDesc('last_activity')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function recoveryCodes(): array
    {
        if (! $this->showingRecoveryCodes || ! $this->user()->hasTwoFactorSecret()) {
            return [];
        }

        return $this->user()->recoveryCodes();
    }

    private function user(): User
    {
        return auth()->user();
    }
}
