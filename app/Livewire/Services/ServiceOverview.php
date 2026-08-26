<?php

namespace App\Livewire\Services;

use App\Actions\Services\FindServicesWithCatalogChanges;
use App\Enums\CustomerServiceStatus;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Category;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
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
 * Zentrale Übersicht aller Kundenleistungen.
 *
 * Beantwortet den wirtschaftlichen Kernzweck der Anwendung: welche Leistungen
 * bei welchem Kunden zu welchem Preis bestehen.
 */
#[Layout('components.layouts.app')]
#[Title('Leistungsübersicht')]
class ServiceOverview extends Component
{
    use WithConfigurableTable, WithPagination;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'active')]
    public string $status = 'active';

    #[Url(as: 'artikel', except: '')]
    public string $productId = '';

    #[Url(as: 'kategorie', except: '')]
    public string $categoryId = '';

    #[Url(as: 'tag', except: '')]
    public string $tagId = '';

    #[Url(as: 'verantwortlich', except: '')]
    public string $responsibleUserId = '';

    #[Url(as: 'abrechnung', except: '')]
    public string $billingFilter = '';

    #[Url(as: 'katalog', except: '')]
    public string $catalogFilter = '';

    /** @var array{column: string, direction: string} */
    public array $sort = ['column' => 'name', 'direction' => 'asc'];

    /**
     * Zwischenspeicher fuer den Katalogabgleich; nur fuer die Dauer eines
     * Aufbaus gueltig und deshalb bewusst nicht oeffentlich.
     *
     * @var array<int, int>|null
     */
    private ?array $catalogChangeIds = null;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function updated(string $property): void
    {
        if ($property !== 'sort') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('catalogFilter', 'search', 'status', 'productId', 'categoryId', 'tagId', 'responsibleUserId', 'billingFilter');
        $this->resetPage();
    }

    public function render(): View
    {
        $services = $this->services();

        return view('livewire.services.service-overview', [
            'services' => $services,
            'summe' => $this->summarise(),
            'statusOptions' => CustomerServiceStatus::options(),
            'products' => $this->products(),
            'categories' => $this->categories(),
            'responsibleUsers' => $this->responsibleUsers(),
            'catalogChangeCount' => $this->catalogChangeCount(),
        ]);
    }

    /**
     * Zahl der Leistungen mit offener Katalogaenderung — unabhaengig von den
     * uebrigen Filtern, weil der Hinweis sonst verschwaende, sobald jemand
     * filtert.
     */
    public function catalogChangeCount(): int
    {
        return count($this->servicesWithCatalogChanges());
    }

    /**
     * Die Leistungen mit offener Katalogaenderung, einmal je Aufbau ermittelt.
     *
     * Der Vergleich laesst sich nicht in SQL fuehren und laedt deshalb alle
     * nicht archivierten Leistungen mit Katalogherkunft. Ohne diesen Zwischen-
     * speicher liefe er zweimal: einmal fuer den Hinweis ueber der Tabelle und
     * einmal fuer den Filter darunter.
     *
     * @return array<int, int>
     */
    private function servicesWithCatalogChanges(): array
    {
        return $this->catalogChangeIds ??= app(FindServicesWithCatalogChanges::class)();
    }

    public function toggleCatalogFilter(): void
    {
        $this->catalogFilter = $this->catalogFilter === 'changed' ? '' : 'changed';
        $this->resetPage();
    }

    protected function tableKey(): string
    {
        return 'service_overview';
    }

    /**
     * Spalten der Liste.
     *
     * Die ersten sieben sind voreingestellt sichtbar; die uebrigen bleiben
     * zuschaltbar. „Abrechnung" gehoert bewusst in die Voreinstellung — sie
     * erklaert, warum eine Leistung *nicht* in den Kennzahlen steht.
     *
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool, default_visible?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'customer' => ['label' => 'Kunde', 'sortable' => false, 'fixed' => true],
            'name' => ['label' => 'Leistung', 'fixed' => true],
            'interval' => ['label' => 'Turnus', 'sortable' => false],
            'sales_price_cents' => ['label' => 'Verkauf'],
            'monthly' => ['label' => 'Monatswert', 'sortable' => false],
            'status' => ['label' => 'Status'],
            'billing' => ['label' => 'Abrechnung', 'sortable' => false],
            'product' => ['label' => 'Katalogartikel', 'sortable' => false, 'default_visible' => false],
            'category' => ['label' => 'Kategorie', 'sortable' => false, 'default_visible' => false],
            'purchase_price_cents' => ['label' => 'Einkauf', 'default_visible' => false],
            'margin' => ['label' => 'Marge', 'sortable' => false, 'default_visible' => false],
            'responsible' => ['label' => 'Verantwortlich', 'sortable' => false, 'default_visible' => false],
            'service_start_date' => ['label' => 'Leistungsbeginn', 'default_visible' => false],
        ];
    }

    /**
     * Rasteranteil und Ausrichtung je Spalte — dieselbe Bauart wie Kundenliste,
     * Artikelkatalog und Projektliste.
     *
     * @return array<string, array{breite: string, rechts?: bool}>
     */
    public function columnLayout(): array
    {
        return [
            'customer' => ['breite' => '1.25fr'],
            'name' => ['breite' => '1.8fr'],
            'interval' => ['breite' => '0.8fr'],
            'sales_price_cents' => ['breite' => '0.9fr', 'rechts' => true],
            'monthly' => ['breite' => '0.9fr', 'rechts' => true],
            'status' => ['breite' => '0.9fr'],
            'billing' => ['breite' => '0.95fr'],
            'product' => ['breite' => '1.3fr'],
            'category' => ['breite' => '1.2fr'],
            'purchase_price_cents' => ['breite' => '0.9fr', 'rechts' => true],
            'margin' => ['breite' => '1.1fr', 'rechts' => true],
            'responsible' => ['breite' => '1fr'],
            'service_start_date' => ['breite' => '0.9fr'],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, CustomerService>
     */
    private function services(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->with(['customer', 'product', 'productVariant', 'category', 'subcategory', 'tags', 'responsibleUser'])
            ->orderBy($this->sortColumn(), $this->sortDirection())
            ->paginate(25);
    }

    /**
     * Summen über alle gefilterten, abrechnungsrelevanten Leistungen — nicht
     * nur über die aktuelle Seite.
     *
     * @return array{monthlyRevenue: Money, yearlyRevenue: Money, monthlyCosts: Money, monthlyMargin: Money, count: int}
     */
    private function summarise(): array
    {
        $umsatz = Money::zero();
        $kosten = Money::zero();
        $anzahl = 0;

        $this->baseQuery()
            ->select(['id', 'purchase_price_cents', 'sales_price_cents', 'billing_interval_unit', 'billing_interval_count', 'status', 'do_not_bill'])
            ->chunkById(500, function ($services) use (&$umsatz, &$kosten, &$anzahl): void {
                foreach ($services as $service) {
                    if (! $service->countsTowardsRevenue()) {
                        continue;
                    }

                    $umsatz = $umsatz->plus($service->monthlyRevenue());
                    $kosten = $kosten->plus($service->monthlyCosts());
                    $anzahl++;
                }
            });

        return [
            'monthlyRevenue' => $umsatz,
            'yearlyRevenue' => $umsatz->multipliedBy(12),
            'monthlyCosts' => $kosten,
            'monthlyMargin' => $umsatz->minus($kosten),
            'count' => $anzahl,
        ];
    }

    /**
     * @return Builder<CustomerService>
     */
    private function baseQuery(): Builder
    {
        return CustomerService::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('billing_label', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $customers) => $customers
                            ->where('company_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('short_label', 'like', $term)
                            ->orWhere('customer_number', 'like', $term));
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->productId !== '', fn (Builder $query) => $query->where('product_id', $this->productId))
            ->when($this->categoryId !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->where('category_id', $this->categoryId)
                    ->orWhere('subcategory_id', $this->categoryId),
            ))
            ->when($this->tagId !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereKey($this->tagId),
            ))
            ->when($this->responsibleUserId !== '', fn (Builder $query) => $query
                ->where('responsible_user_id', $this->responsibleUserId))
            ->when($this->billingFilter === 'do_not_bill', fn (Builder $query) => $query->where('do_not_bill', true))
            ->when($this->billingFilter === 'billable', fn (Builder $query) => $query->where('do_not_bill', false))
            ->when($this->billingFilter === 'once', fn (Builder $query) => $query->where('billing_interval_unit', 'once'))
            // Genau die Leistungen, die die Abrechnungsgrafik nicht einplanen
            // kann — die Uebersicht ist der Weg, sie nachzutragen.
            ->when($this->billingFilter === 'no_schedule', fn (Builder $query) => $query->withoutBillingSchedule())
            ->when(
                $this->catalogFilter === 'changed',
                fn (Builder $query) => $query->whereKey($this->servicesWithCatalogChanges()),
            );
    }

    /**
     * Die Richtung kommt wie die Spalte aus der URL. Laravel wirft bei einem
     * fremden Wert eine Ausnahme — die Seite antwortete mit 500 statt mit
     * einer Liste.
     */
    private function sortDirection(): string
    {
        return $this->sort['direction'] === 'desc' ? 'desc' : 'asc';
    }

    private function sortColumn(): string
    {
        $sortable = [
            'name', 'status', 'purchase_price_cents', 'sales_price_cents',
            'service_start_date', 'created_at',
        ];

        return in_array($this->sort['column'], $sortable, strict: true)
            ? $this->sort['column']
            : 'name';
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categories(): Collection
    {
        return Category::query()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => ['id' => $category->id, 'label' => $category->path()]);
    }

    /**
     * @return Collection<int, User>
     */
    private function responsibleUsers(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
