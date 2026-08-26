<?php

namespace App\Livewire\Registrar;

use App\Enums\RegistrarProvider;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Livewire\Concerns\WithInventoryAssignment;
use App\Models\Certificate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Der importierte Zertifikatsbestand.
 *
 * Wie bei den Domains: Ablauf und Zuordnung sind die beiden Fragen, die die
 * Liste beantworten soll.
 */
#[Layout('components.layouts.app')]
#[Title('Zertifikate')]
class CertificateList extends Component
{
    use WithConfigurableTable, WithInventoryAssignment, WithPagination;

    /**
     * Ab wie vielen Tagen eine Domain als „läuft bald ab" gilt.
     */
    private const BALD = 30;

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

    /**
     * @return class-string<Certificate>
     */
    protected function inventoryModel(): string
    {
        return Certificate::class;
    }

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
        return view('livewire.registrar.certificate-list', [
            'certificates' => $this->certificates(),
            'metrics' => $this->metrics(),
            'providerOptions' => RegistrarProvider::options(),
        ]);
    }

    /**
     * @return array{total: int, withoutService: int, unassigned: int, expiringSoon: int, expired: int}
     */
    public function metrics(): array
    {
        return [
            'total' => Certificate::query()->count(),
            'withoutService' => Certificate::query()->withoutService()->count(),
            'unassigned' => Certificate::query()->unassigned()->count(),
            'expiringSoon' => Certificate::query()->expiringWithin(self::BALD)->count(),
            'expired' => Certificate::query()
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', now()->toDateString())
                ->count(),
        ];
    }

    protected function tableKey(): string
    {
        return 'certificates';
    }

    /**
     * @return array<string, array{label: string, sortable?: bool, fixed?: bool, default_visible?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'certificate' => ['label' => 'Hauptname', 'sortable' => false, 'fixed' => true],
            'customer' => ['label' => 'Kunde', 'sortable' => false],
            'service' => ['label' => 'Leistung', 'sortable' => false, 'default_visible' => false],
            'expires_on' => ['label' => 'Läuft ab'],
            'issuer' => ['label' => 'Aussteller', 'sortable' => false],
            'provider' => ['label' => 'Anbieter'],
            'status' => ['label' => 'Status', 'default_visible' => false],
            'alternative_names' => ['label' => 'Weitere Namen', 'sortable' => false, 'default_visible' => false],
            'issued_on' => ['label' => 'Ausgestellt', 'default_visible' => false],
            'synced_at' => ['label' => 'Abgeglichen', 'default_visible' => false],
        ];
    }

    /**
     * @return array<string, array{breite: string, rechts?: bool}>
     */
    public function columnLayout(): array
    {
        return [
            'certificate' => ['breite' => '1.9fr'],
            'customer' => ['breite' => '1.3fr'],
            'expires_on' => ['breite' => '1.1fr'],
            'issuer' => ['breite' => '1fr'],
            'provider' => ['breite' => '0.9fr'],
            'status' => ['breite' => '0.9fr'],
            'alternative_names' => ['breite' => '1.6fr'],
            'issued_on' => ['breite' => '0.9fr'],
            'synced_at' => ['breite' => '1fr'],
        ];
    }

    /**
     * Sortierspalte und -richtung kommen aus einer oeffentlichen Eigenschaft
     * und sind damit vom Browser setzbar. Ungeprueft weitergereicht brechen
     * ein unbekannter Spaltenname oder eine unbekannte Richtung die Abfrage.
     */
    private function sortColumn(): string
    {
        $sortierbar = ['common_name', 'expires_on', 'issued_on', 'provider', 'status', 'synced_at'];

        return in_array($this->sort['column'], $sortierbar, strict: true)
            ? $this->sort['column']
            : 'expires_on';
    }

    private function sortDirection(): string
    {
        return $this->sort['direction'] === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @return LengthAwarePaginator<int, Certificate>
     */
    private function certificates(): LengthAwarePaginator
    {
        return Certificate::query()
            ->with([
                'customer:id,company_name,first_name,last_name,customer_number,type',
                'customerService:id,name,billing_label',
            ])
            ->when($this->search !== '', fn (Builder $query) => $query->where('common_name', 'like', '%'.$this->search.'%'))
            ->when($this->provider !== '', fn (Builder $query) => $query->where('provider', $this->provider))
            ->when($this->assignment === 'unassigned', fn (Builder $query) => $query->unassigned())
            ->when($this->assignment === 'assigned', fn (Builder $query) => $query->whereNotNull('customer_id'))
            ->when($this->assignment === 'without_service', fn (Builder $query) => $query->withoutService())
            ->when($this->expiry === 'soon', fn (Builder $query) => $query->expiringWithin(self::BALD))
            ->when($this->expiry === 'expired', fn (Builder $query) => $query
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', now()->toDateString()))
            // Ohne Ablaufdatum ans Ende: sie sind kein Termin, sondern eine Lücke.
            ->orderByRaw('expires_on is null')
            ->orderBy($this->sortColumn(), $this->sortDirection())
            ->paginate(25);
    }
}
