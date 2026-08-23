<?php

namespace App\Livewire\Contacts;

use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Contact;
use App\Models\ContactRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Zentrale Liste aller Ansprechpartner.
 */
#[Layout('components.layouts.app')]
#[Title('Ansprechpartner')]
class ContactList extends Component
{
    use WithConfigurableTable, WithPagination;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'active')]
    public string $status = 'active';

    #[Url(as: 'rolle', except: '')]
    public string $roleId = '';

    /** @var array{column: string, direction: string} */
    public array $sort = ['column' => 'last_name', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedRoleId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'roleId');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.contacts.contact-list', [
            'contacts' => $this->contacts(),
            'roles' => $this->roles(),
        ]);
    }

    protected function tableKey(): string
    {
        return 'contacts';
    }

    /**
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'name' => ['label' => 'Name', 'sortable' => false, 'fixed' => true],
            'email' => ['label' => 'E-Mail-Adresse', 'sortable' => false],
            'phone' => ['label' => 'Telefon', 'sortable' => false, 'width' => 180],
            'customers' => ['label' => 'Kunden', 'sortable' => false],
            'roles' => ['label' => 'Rollen', 'sortable' => false],
            'preferred_contact_method' => ['label' => 'Bevorzugt', 'width' => 130],
            'status' => ['label' => 'Status', 'sortable' => false, 'width' => 120],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    private function contacts(): LengthAwarePaginator
    {
        return Contact::query()
            ->with([
                'emailAddresses',
                'phoneNumbers',
                'assignments.customer',
                'assignments.roles',
            ])
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->status === 'active', fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($this->status === 'archived', fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->when($this->roleId !== '', fn (Builder $query) => $query->whereHas(
                'assignments.roles',
                fn (Builder $roles) => $roles->whereKey($this->roleId),
            ))
            ->orderBy($this->sortColumn(), $this->sort['direction'])
            ->orderBy('first_name')
            ->paginate(25);
    }

    /**
     * @param  Builder<Contact>  $query
     */
    private function applySearch(Builder $query): void
    {
        $term = '%'.$this->search.'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhereHas('emailAddresses', fn (Builder $emails) => $emails->where('email', 'like', $term))
                ->orWhereHas('phoneNumbers', fn (Builder $phones) => $phones->where('number', 'like', $term))
                ->orWhereHas('assignments.customer', fn (Builder $customers) => $customers
                    ->where('company_name', 'like', $term)
                    ->orWhere('customer_number', 'like', $term));
        });
    }

    private function sortColumn(): string
    {
        $sortable = ['last_name', 'first_name', 'preferred_contact_method', 'created_at'];

        return in_array($this->sort['column'], $sortable, strict: true)
            ? $this->sort['column']
            : 'last_name';
    }

    /**
     * @return Collection<int, ContactRole>
     */
    private function roles(): Collection
    {
        return ContactRole::query()->active()->orderBy('sort_order')->orderBy('name')->get();
    }
}
