<?php

namespace App\Livewire\Subscriptions;

use App\Actions\Subscriptions\DeleteSubscriptions;
use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Customer;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Subscriptions')]
class SubscriptionList extends Component
{
    use WithConfigurableTable, WithPagination;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'server', except: '')]
    public ?string $server = '';

    #[Url(as: 'status', except: '')]
    public ?string $status = '';

    #[Url(as: 'kunde', except: '')]
    public ?string $customerFilter = '';

    public array $selectedIds = [];

    #[Locked]
    public array $pendingDeletionIds = [];

    public bool $showDeleteConfirmation = false;

    public function togglePageSelection(): void
    {
        $ids = $this->subscriptions()->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $this->selectedIds = count($ids) > 0 && array_diff($ids, $this->selectedIds) === [] ? [] : $ids;
        $this->pendingDeletionIds = [];
        $this->showDeleteConfirmation = false;
    }

    public function updatedSelectedIds(): void
    {
        $this->pendingDeletionIds = [];
        $this->showDeleteConfirmation = false;
    }

    public function confirmDeletion(): void
    {
        abort_unless(auth()->check(), 403);
        $visibleIds = $this->subscriptions()->getCollection()->pluck('id')->all();
        $this->validate([
            'selectedIds' => ['required', 'array', 'min:1', 'max:100'],
            'selectedIds.*' => ['required', 'integer', 'distinct', Rule::in($visibleIds)],
        ]);
        $this->pendingDeletionIds = array_map('intval', $this->selectedIds);
        $this->showDeleteConfirmation = true;
        $this->resetValidation();
    }

    public function deleteSelected(DeleteSubscriptions $delete): void
    {
        abort_unless(auth()->check(), 403);
        if (! $this->showDeleteConfirmation || $this->pendingDeletionIds === []) {
            throw ValidationException::withMessages(['selectedIds' => 'Bitte die Auswahl zuerst zum Löschen bestätigen.']);
        }
        $count = $delete($this->pendingDeletionIds);
        $this->clearSelection();
        $this->resetPage();
        session()->flash('subscriptions-deleted', $count.' Subscriptions gelöscht.');
    }

    public function updatedPaginators(): void
    {
        $this->clearSelection();
    }

    protected function clearSelection(): void
    {
        $this->reset('selectedIds', 'pendingDeletionIds', 'showDeleteConfirmation');
        $this->resetValidation();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'server', 'status', 'customerFilter'], true)) {
            $this->clearSelection();
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->clearSelection();
        $this->reset('search', 'server', 'status', 'customerFilter');
        $this->resetPage();
    }

    protected function tableKey(): string
    {
        return 'subscriptions';
    }

    protected function columnDefinitions(): array
    {
        return [
            'name' => ['label' => 'Hauptdomain', 'fixed' => true, 'sortable' => false],
            'server' => ['label' => 'Server', 'sortable' => false],
            'status' => ['label' => 'Status', 'sortable' => false],
            'customer' => ['label' => 'Kunde', 'sortable' => false],
            'billing' => ['label' => 'Abrechnung', 'sortable' => false],
            'service_plan' => ['label' => 'Plesk-Tarif', 'sortable' => false],
            'subscriber' => ['label' => 'Plesk-Abonnent', 'sortable' => false],
            'hosting_login' => ['label' => 'Hosting-Login', 'sortable' => false, 'default_visible' => false],
            'setup_date' => ['label' => 'In Plesk angelegt am', 'sortable' => false, 'default_visible' => false],
            'ip_address' => ['label' => 'IP-Adresse', 'sortable' => false, 'default_visible' => false],
            'expires_on' => ['label' => 'Läuft ab', 'sortable' => false, 'default_visible' => false],
            'plesk_id' => ['label' => 'Plesk-ID', 'sortable' => false, 'default_visible' => false],
        ];
    }

    protected function subscriptions(): LengthAwarePaginator
    {
        return Subscription::query()
            ->with(['customer', 'customerService'])
            ->when(filled($this->search), fn (Builder $query) => $query->searchName($this->search))
            ->when(filled($this->server), fn (Builder $query) => $query->where('server', $this->server))
            ->when(filled($this->status), fn (Builder $query) => $query->where('status', $this->status))
            ->when(filled($this->customerFilter), fn (Builder $query) => $query->where('customer_id', $this->customerFilter))
            ->orderBy('name')->orderBy('server')->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.subscriptions.subscription-list', [
            'subscriptions' => $this->subscriptions(),
            'pendingSubscriptions' => Subscription::query()->whereIn('id', $this->pendingDeletionIds)->orderBy('server')->orderBy('name')->get(),
            'serverOptions' => PleskServer::options(),
            'statusOptions' => SubscriptionStatus::options(),
            'customerOptions' => Customer::query()->orderBy('company_name')->orderBy('last_name')->get()
                ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->customer_number.' · '.$customer->displayName()]),
        ]);
    }
}
