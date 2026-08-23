<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Customer;
use App\Models\User;
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
 * Zentrale Kundenliste mit Suche, Filtern, Sortierung und Pagination.
 */
#[Layout('components.layouts.app')]
#[Title('Kunden')]
class CustomerList extends Component
{
    use WithConfigurableTable, WithPagination;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'active')]
    public string $status = 'active';

    #[Url(as: 'typ', except: '')]
    public string $type = '';

    #[Url(as: 'verantwortlich', except: '')]
    public string $responsibleUserId = '';

    /** @var array{column: string, direction: string} */
    public array $sort = ['column' => 'customer_number', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedResponsibleUserId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'type', 'responsibleUserId');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.customers.customer-list', [
            'customers' => $this->customers(),
            'responsibleUsers' => $this->responsibleUsers(),
            'statusOptions' => CustomerStatus::options(),
            'typeOptions' => CustomerType::options(),
        ]);
    }

    protected function tableKey(): string
    {
        return 'customers';
    }

    /**
     * Kennzahlen zu Leistungen, Umsatz, Kosten und Marge werden mit den
     * Kundenleistungen befuellt (Meilenstein 5/6) und stehen bis dahin auf 0.
     *
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'customer_number' => ['label' => 'Kundennummer', 'width' => 150, 'fixed' => true],
            'name' => ['label' => 'Name', 'sortable' => false, 'fixed' => true],
            'short_label' => ['label' => 'Kurzbezeichnung'],
            'internal_code' => ['label' => 'Kürzel', 'width' => 110],
            'type' => ['label' => 'Typ', 'width' => 140],
            'status' => ['label' => 'Status', 'width' => 120],
            'responsible' => ['label' => 'Verantwortlich', 'sortable' => false],
            'services_count' => ['label' => 'Leistungen', 'sortable' => false, 'width' => 120],
            'monthly_revenue' => ['label' => 'Monatsumsatz', 'sortable' => false, 'width' => 150],
            'yearly_revenue' => ['label' => 'Jahresumsatz', 'sortable' => false, 'width' => 150],
            'monthly_costs' => ['label' => 'Kosten', 'sortable' => false, 'width' => 140],
            'margin' => ['label' => 'Marge', 'sortable' => false, 'width' => 140],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    private function customers(): LengthAwarePaginator
    {
        return Customer::query()
            ->with('responsibleUser')
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->responsibleUserId !== '', fn (Builder $query) => $query->where('responsible_user_id', $this->responsibleUserId))
            ->orderBy($this->sortColumn(), $this->sort['direction'])
            ->paginate(25);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    private function applySearch(Builder $query): void
    {
        $term = '%'.$this->search.'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('customer_number', 'like', $term)
                ->orWhere('company_name', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('short_label', 'like', $term)
                ->orWhere('internal_code', 'like', $term);
        });
    }

    /**
     * Nur bekannte Spalten zulassen — die Sortierung kommt aus der URL.
     */
    private function sortColumn(): string
    {
        $sortable = ['customer_number', 'short_label', 'internal_code', 'type', 'status', 'created_at'];

        return in_array($this->sort['column'], $sortable, strict: true)
            ? $this->sort['column']
            : 'customer_number';
    }

    /**
     * @return Collection<int, User>
     */
    private function responsibleUsers(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
