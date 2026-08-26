<?php

namespace App\Livewire\Registrar;

use App\Enums\RegistrarProvider;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Domain;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Der importierte Domainbestand.
 *
 * Die Liste beantwortet vor allem zwei Fragen: was läuft demnächst ab, und was
 * ist noch keinem Kunden zugeordnet. Das Zweite ist nach einem Import die
 * eigentliche Arbeit — der Registrar kennt unsere Kundennummern nicht.
 */
#[Layout('components.layouts.app')]
#[Title('Domains')]
class DomainList extends Component
{
    use WithConfigurableTable, WithPagination;

    /**
     * Ab wie vielen Tagen eine Domain als „läuft bald ab" gilt.
     */
    private const BALD = 60;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'anbieter', except: '')]
    public string $provider = '';

    #[Url(as: 'zuordnung', except: '')]
    public string $assignment = '';

    #[Url(as: 'ablauf', except: '')]
    public string $expiry = '';

    /** @var array{column: string, direction: string} */
    public array $sort = ['column' => 'expires_on', 'direction' => 'asc'];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'provider', 'assignment', 'expiry'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'provider', 'assignment', 'expiry');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.registrar.domain-list', [
            'domains' => $this->domains(),
            'metrics' => $this->metrics(),
            'providerOptions' => RegistrarProvider::options(),
        ]);
    }

    /**
     * @return array{total: int, unassigned: int, expiringSoon: int, expired: int}
     */
    public function metrics(): array
    {
        return [
            'total' => Domain::query()->count(),
            'unassigned' => Domain::query()->unassigned()->count(),
            'expiringSoon' => Domain::query()->expiringWithin(self::BALD)->count(),
            'expired' => Domain::query()
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', now()->toDateString())
                ->count(),
        ];
    }

    protected function tableKey(): string
    {
        return 'domains';
    }

    /**
     * @return array<string, array{label: string, sortable?: bool, fixed?: bool, default_visible?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'domain' => ['label' => 'Domain', 'sortable' => false, 'fixed' => true],
            'customer' => ['label' => 'Kunde', 'sortable' => false],
            'expires_on' => ['label' => 'Läuft ab'],
            'auto_renew' => ['label' => 'Verlängerung', 'sortable' => false],
            'provider' => ['label' => 'Anbieter'],
            'status' => ['label' => 'Status', 'default_visible' => false],
            'nameservers' => ['label' => 'Nameserver', 'sortable' => false, 'default_visible' => false],
            'registered_on' => ['label' => 'Registriert', 'default_visible' => false],
            'synced_at' => ['label' => 'Abgeglichen', 'default_visible' => false],
        ];
    }

    /**
     * @return array<string, array{breite: string, rechts?: bool}>
     */
    public function columnLayout(): array
    {
        return [
            'domain' => ['breite' => '1.9fr'],
            'customer' => ['breite' => '1.3fr'],
            'expires_on' => ['breite' => '1.1fr'],
            'auto_renew' => ['breite' => '0.9fr'],
            'provider' => ['breite' => '0.9fr'],
            'status' => ['breite' => '0.9fr'],
            'nameservers' => ['breite' => '1.6fr'],
            'registered_on' => ['breite' => '0.9fr'],
            'synced_at' => ['breite' => '1fr'],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Domain>
     */
    private function domains(): LengthAwarePaginator
    {
        return Domain::query()
            ->with(['customer:id,company_name,first_name,last_name,customer_number,type'])
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->provider !== '', fn (Builder $query) => $query->where('provider', $this->provider))
            ->when($this->assignment === 'unassigned', fn (Builder $query) => $query->unassigned())
            ->when($this->assignment === 'assigned', fn (Builder $query) => $query->whereNotNull('customer_id'))
            ->when($this->expiry === 'soon', fn (Builder $query) => $query->expiringWithin(self::BALD))
            ->when($this->expiry === 'expired', fn (Builder $query) => $query
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', now()->toDateString()))
            // Ohne Ablaufdatum ans Ende: sie sind kein Termin, sondern eine Lücke.
            ->orderByRaw('expires_on is null')
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(25);
    }
}
