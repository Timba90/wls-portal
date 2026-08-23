<?php

namespace App\Livewire\Contacts;

use App\Actions\Contacts\SaveContactDeputies;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\ContactRole;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Ansprechpartner-Bereich der Kundendetailseite.
 *
 * Zeigt die Zuordnungen inklusive Rollen und Primaerkontakten und verwaltet die
 * Vertretungen je Rolle.
 */
class CustomerContacts extends Component
{
    public Customer $customer;

    public bool $showDeputies = false;

    /** @var array<int, array{contact_role_id: string, contact_id: string, priority: int}> */
    public array $deputies = [];

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
        $this->loadDeputies();
    }

    public function openDeputies(): void
    {
        $this->loadDeputies();
        $this->showDeputies = true;
    }

    public function addDeputy(): void
    {
        $this->deputies[] = ['contact_role_id' => '', 'contact_id' => '', 'priority' => 100];
    }

    public function removeDeputy(int $index): void
    {
        unset($this->deputies[$index]);
        $this->deputies = array_values($this->deputies);
    }

    public function saveDeputies(SaveContactDeputies $saveContactDeputies): void
    {
        $this->validate([
            'deputies.*.contact_role_id' => ['required', 'exists:contact_roles,id'],
            'deputies.*.contact_id' => ['required', 'exists:contacts,id'],
            'deputies.*.priority' => ['required', 'integer', 'min:1', 'max:999'],
        ], attributes: [
            'deputies.*.contact_role_id' => 'Rolle',
            'deputies.*.contact_id' => 'Vertretung',
            'deputies.*.priority' => 'Priorität',
        ]);

        $saveContactDeputies($this->customer, array_map(
            fn (array $deputy): array => [
                'contact_role_id' => (int) $deputy['contact_role_id'],
                'contact_id' => (int) $deputy['contact_id'],
                'priority' => (int) $deputy['priority'],
            ],
            $this->deputies,
        ));

        $this->showDeputies = false;

        $this->dispatch('vertretungen-gespeichert');
    }

    public function detachAssignment(int $assignmentId): void
    {
        ContactAssignment::query()
            ->whereKey($assignmentId)
            ->where('customer_id', $this->customer->getKey())
            ->delete();

        $this->dispatch('zuordnung-entfernt');
    }

    public function render(): View
    {
        return view('livewire.contacts.customer-contacts', [
            'assignments' => $this->assignments(),
            'deputyGroups' => $this->deputyGroups(),
            'roles' => $this->roles(),
            'availableContacts' => $this->availableContacts(),
        ]);
    }

    /**
     * @return Collection<int, ContactAssignment>
     */
    private function assignments(): Collection
    {
        return $this->customer
            ->contactAssignments()
            ->with(['contact.emailAddresses', 'contact.phoneNumbers', 'roles', 'primaryEmail', 'primaryPhone'])
            ->orderByDesc('is_primary_contact')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Vertretungen nach Rolle gruppiert, innerhalb der Rolle nach Priorität.
     *
     * @return Collection<int, array{role: ContactRole, deputies: Collection<int, mixed>}>
     */
    private function deputyGroups(): Collection
    {
        return $this->customer
            ->contactDeputies()
            ->with(['role', 'contact'])
            ->orderBy('priority')
            ->get()
            ->groupBy('contact_role_id')
            ->map(fn (Collection $group): array => [
                'role' => $group->first()->role,
                'deputies' => $group,
            ])
            ->values();
    }

    /**
     * @return Collection<int, ContactRole>
     */
    private function roles(): Collection
    {
        return ContactRole::query()->active()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Als Vertretung kommen die diesem Kunden zugeordneten Ansprechpartner in
     * Frage.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    private function availableContacts(): Collection
    {
        return $this->customer
            ->contacts()
            ->active()
            ->orderBy('last_name')
            ->get()
            ->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'label' => $contact->listName(),
            ]);
    }

    private function loadDeputies(): void
    {
        $this->deputies = $this->customer
            ->contactDeputies()
            ->orderBy('priority')
            ->get()
            ->map(fn ($deputy): array => [
                'contact_role_id' => (string) $deputy->contact_role_id,
                'contact_id' => (string) $deputy->contact_id,
                'priority' => $deputy->priority,
            ])
            ->all();
    }
}
