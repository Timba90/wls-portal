<?php

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Eigenes Profil: Stammdaten und Passwortwechsel.
 */
#[Layout('components.layouts.app')]
#[Title('Profil')]
class ProfilePage extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = $this->user()->name;
        $this->email = $this->user()->email;
    }

    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
        ]);

        $this->user()->forceFill($validated)->save();

        $this->dispatch('profil-gespeichert');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ], attributes: [
            'current_password' => 'Aktuelles Passwort',
            'password' => 'Neues Passwort',
        ]);

        $this->user()->forceFill(['password' => $this->password])->save();

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('passwort-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.profile.profile-page');
    }

    private function user(): User
    {
        return auth()->user();
    }
}
