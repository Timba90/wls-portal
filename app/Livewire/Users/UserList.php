<?php

namespace App\Livewire\Users;

use App\Actions\Auth\CreateUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Benutzerverwaltung. Es gibt keine öffentliche Registrierung — Benutzer legen
 * andere Benutzer an. Alle Benutzer haben dieselben Rechte.
 */
#[Layout('components.layouts.app')]
#[Title('Benutzer')]
class UserList extends Component
{
    use WithPagination;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->resetForm();
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->showForm = true;
    }

    public function save(CreateUser $createUser): void
    {
        $validated = $this->validate($this->rules(), attributes: [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'password' => 'Passwort',
        ]);

        if ($this->editingUserId) {
            User::query()->findOrFail($this->editingUserId)->forceFill(array_filter([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'] ?? null,
            ]))->save();
        } else {
            $createUser($validated['name'], $validated['email'], $validated['password']);
        }

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('benutzer-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.users.user-list', [
            'users' => $this->users(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    private function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($this->editingUserId),
            ],
            'password' => $this->editingUserId
                ? ['nullable', 'string', Password::default(), 'confirmed']
                : ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    private function resetForm(): void
    {
        $this->reset('editingUserId', 'name', 'email', 'password', 'password_confirmation');
        $this->resetValidation();
    }
}
