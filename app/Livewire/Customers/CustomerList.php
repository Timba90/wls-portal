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
     * Spalten der Liste.
     *
     * Die ersten sechs bilden den Entwurf ab und sind voreingestellt sichtbar;
     * die uebrigen bleiben zuschaltbar, damit die global konfigurierbaren
     * Spalten aus Meilenstein 2 erhalten bleiben.
     *
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'customer' => ['label' => 'Kunde', 'sortable' => false, 'fixed' => true],
            'contact' => ['label' => 'Ansprechpartner', 'sortable' => false],
            'active_services_count' => ['label' => 'Leistungen'],
            'monthly_revenue' => ['label' => 'Umsatz / Mon', 'sortable' => false],
            'activity' => ['label' => 'Letzte Aktivität', 'sortable' => false],
            'status' => ['label' => 'Status'],
            'customer_number' => ['label' => 'Kundennummer', 'default_visible' => false],
            'internal_code' => ['label' => 'Kürzel', 'default_visible' => false],
            'type' => ['label' => 'Typ', 'default_visible' => false],
            'responsible' => ['label' => 'Verantwortlich', 'sortable' => false, 'default_visible' => false],
            'yearly_revenue' => ['label' => 'Umsatz / Jahr', 'sortable' => false, 'default_visible' => false],
            'monthly_costs' => ['label' => 'Kosten / Mon', 'sortable' => false, 'default_visible' => false],
            'margin' => ['label' => 'Marge / Mon', 'sortable' => false, 'default_visible' => false],
        ];
    }

    /**
     * Rasteranteil und Ausrichtung je Spalte, entsprechend dem Entwurf.
     *
     * @return array<string, array{breite: string, rechts?: bool}>
     */
    public function columnLayout(): array
    {
        return [
            'customer' => ['breite' => '1.8fr'],
            'contact' => ['breite' => '1.3fr'],
            'active_services_count' => ['breite' => '0.7fr', 'rechts' => true],
            'monthly_revenue' => ['breite' => '1.1fr', 'rechts' => true],
            'activity' => ['breite' => '0.9fr'],
            'status' => ['breite' => '0.9fr'],
            'customer_number' => ['breite' => '0.9fr'],
            'internal_code' => ['breite' => '0.7fr'],
            'type' => ['breite' => '0.8fr'],
            'responsible' => ['breite' => '1fr'],
            'yearly_revenue' => ['breite' => '0.9fr', 'rechts' => true],
            'monthly_costs' => ['breite' => '0.9fr', 'rechts' => true],
            'margin' => ['breite' => '0.9fr', 'rechts' => true],
        ];
    }

    /**
     * Zaehler der Statusfilter fuer die Schnellauswahl ueber der Tabelle.
     *
     * Beruecksichtigt Suche, Typ und Verantwortlichen, damit die Zahlen zu dem
     * passen, was der Statuswechsel tatsaechlich zeigen wuerde.
     *
     * @return array<int, array{wert: string, label: string, anzahl: int}>
     */
    public function statusFilters(): array
    {
        $basis = fn (): Builder => Customer::query()
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->responsibleUserId !== '', fn (Builder $query) => $query->where('responsible_user_id', $this->responsibleUserId));

        return [
            ['wert' => '', 'label' => 'Alle', 'anzahl' => $basis()->count()],
            ['wert' => CustomerStatus::Active->value, 'label' => 'Aktiv', 'anzahl' => $basis()->active()->count()],
            ['wert' => CustomerStatus::Archived->value, 'label' => 'Archiviert', 'anzahl' => $basis()->archived()->count()],
        ];
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    private function customers(): LengthAwarePaginator
    {
        return Customer::query()
            ->with([
                'responsibleUser',
                'services' => fn ($query) => $query->billable(),
                'emailAddresses',
                'contactAssignments' => fn ($query) => $query->where('is_primary_contact', true)->limit(1),
                'contactAssignments.contact.emailAddresses',
            ])
            ->withCount(['services as active_services_count' => fn (Builder $query) => $query->active()])
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
        $sortable = [
            'customer_number', 'short_label', 'internal_code', 'type', 'status',
            'active_services_count', 'created_at', 'updated_at',
        ];

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
