<?php

namespace App\Livewire\Subscriptions;

use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Customer;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
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

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'server', 'status', 'customerFilter'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
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

    public function render(): View
    {
        return view('livewire.subscriptions.subscription-list', [
            'subscriptions' => Subscription::query()
                ->with(['customer', 'customerService'])
                ->when(filled($this->search), fn (Builder $query) => $query->searchName($this->search))
                ->when(filled($this->server), fn (Builder $query) => $query->where('server', $this->server))
                ->when(filled($this->status), fn (Builder $query) => $query->where('status', $this->status))
                ->when(filled($this->customerFilter), fn (Builder $query) => $query->where('customer_id', $this->customerFilter))
                ->orderBy('name')->orderBy('server')->paginate(25),
            'serverOptions' => PleskServer::options(),
            'statusOptions' => SubscriptionStatus::options(),
            'customerOptions' => Customer::query()->orderBy('company_name')->orderBy('last_name')->get()
                ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->customer_number.' · '.$customer->displayName()]),
        ]);
    }
}
