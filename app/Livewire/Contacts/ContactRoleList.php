<?php

namespace App\Livewire\Contacts;

use App\Models\ContactRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Verwaltung der frei definierbaren Ansprechpartnerrollen.
 */
#[Layout('components.layouts.app')]
#[Title('Ansprechpartnerrollen')]
class ContactRoleList extends Component
{
    public bool $showForm = false;

    public ?int $editingRoleId = null;

    public string $name = '';

    public string $description = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $roleId): void
    {
        $role = ContactRole::query()->findOrFail($roleId);

        $this->resetForm();
        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->description = (string) $role->description;
        $this->sort_order = $role->sort_order;
        $this->is_active = $role->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('contact_roles', 'name')->ignore($this->editingRoleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], attributes: [
            'name' => 'Name',
            'description' => 'Beschreibung',
            'sort_order' => 'Sortierung',
        ]);

        $attributes = [...$validated, 'description' => $validated['description'] ?: null];

        if ($this->editingRoleId) {
            ContactRole::query()->findOrFail($this->editingRoleId)->update($attributes);
        } else {
            ContactRole::query()->create($attributes);
        }

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('rolle-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.contacts.contact-role-list', [
            'roles' => $this->roles(),
        ]);
    }

    /**
     * @return Collection<int, ContactRole>
     */
    private function roles(): Collection
    {
        return ContactRole::query()
            ->withCount('assignments')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function resetForm(): void
    {
        $this->reset('editingRoleId', 'name', 'description', 'sort_order', 'is_active');
        $this->resetValidation();
    }
}
