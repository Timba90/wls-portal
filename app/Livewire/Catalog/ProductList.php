<?php

namespace App\Livewire\Catalog;

use App\Enums\CatalogStatus;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
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
 * Liste der Katalogartikel.
 */
#[Layout('components.layouts.app')]
#[Title('Artikel / Leistungen')]
class ProductList extends Component
{
    use WithConfigurableTable, WithPagination;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'active')]
    public string $status = 'active';

    #[Url(as: 'kategorie', except: '')]
    public string $categoryId = '';

    #[Url(as: 'tag', except: '')]
    public string $tagId = '';

    /** @var array{column: string, direction: string} */
    public array $sort = ['column' => 'name', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedTagId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'categoryId', 'tagId');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.catalog.product-list', [
            'products' => $this->products(),
            'categories' => $this->categories(),
            'categoryTotal' => $this->categoryTotal(),
            'statusOptions' => CatalogStatus::options(),
        ]);
    }

    protected function tableKey(): string
    {
        return 'products';
    }

    /**
     * Spalten des Katalogs.
     *
     * Die ersten sechs bilden den Entwurf ab, die uebrigen bleiben zuschaltbar.
     *
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool, default_visible?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'article' => ['label' => 'Artikel', 'sortable' => false, 'fixed' => true],
            'category' => ['label' => 'Kategorie', 'sortable' => false],
            'interval' => ['label' => 'Turnus', 'sortable' => false],
            'default_sales_price_cents' => ['label' => 'Preis'],
            'contracts' => ['label' => 'Verträge', 'sortable' => false],
            'status' => ['label' => 'Status'],
            'default_purchase_price_cents' => ['label' => 'Einkauf', 'default_visible' => false],
            'margin' => ['label' => 'Marge', 'sortable' => false, 'default_visible' => false],
            'variants_count' => ['label' => 'Varianten', 'sortable' => false, 'default_visible' => false],
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
            'article' => ['breite' => '2fr'],
            'category' => ['breite' => '1fr'],
            'interval' => ['breite' => '0.9fr'],
            'default_sales_price_cents' => ['breite' => '0.9fr', 'rechts' => true],
            'contracts' => ['breite' => '0.7fr', 'rechts' => true],
            'status' => ['breite' => '0.9fr'],
            'default_purchase_price_cents' => ['breite' => '0.9fr', 'rechts' => true],
            'margin' => ['breite' => '0.9fr', 'rechts' => true],
            'variants_count' => ['breite' => '0.6fr', 'rechts' => true],
        ];
    }

    /**
     * Zaehler der Statusfilter ueber der Tabelle.
     *
     * Beruecksichtigt Suche, Kategorie und Tag, damit die Zahlen zu dem passen,
     * was ein Statuswechsel tatsaechlich zeigen wuerde.
     *
     * @return array<int, array{wert: string, label: string, anzahl: int}>
     */
    public function statusFilters(): array
    {
        $basis = fn (): Builder => Product::query()
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->categoryId !== '', fn (Builder $query) => $this->applyCategory($query))
            ->when($this->tagId !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereKey($this->tagId),
            ));

        return [
            ['wert' => '', 'label' => 'Alle', 'anzahl' => $basis()->count()],
            ['wert' => CatalogStatus::Active->value, 'label' => 'Aktiv', 'anzahl' => $basis()->active()->count()],
            ['wert' => CatalogStatus::Archived->value, 'label' => 'Archiviert', 'anzahl' => $basis()->archived()->count()],
        ];
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function setCategory(string $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    private function products(): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'subcategory', 'tags'])
            ->withCount(['variants', 'customerServices as contracts_count'])
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->categoryId !== '', fn (Builder $query) => $this->applyCategory($query))
            ->when($this->tagId !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereKey($this->tagId),
            ))
            ->orderBy($this->sortColumn(), $this->sort['direction'])
            ->paginate(25);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySearch(Builder $query): void
    {
        $term = '%'.$this->search.'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', $term)
                ->orWhere('internal_name', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }

    /**
     * Kategorie und Unterkategorie gelten gleichermassen.
     *
     * @param  Builder<Product>  $query
     */
    private function applyCategory(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('category_id', $this->categoryId)
            ->orWhere('subcategory_id', $this->categoryId));
    }

    private function sortColumn(): string
    {
        $sortable = [
            'name', 'internal_name', 'status',
            'default_purchase_price_cents', 'default_sales_price_cents', 'created_at',
        ];

        return in_array($this->sort['column'], $sortable, strict: true)
            ? $this->sort['column']
            : 'name';
    }

    /**
     * Kategorien fuer die Leiste links, in der Reihenfolge des Baums.
     *
     * `anzahl` zaehlt genau das, was ein Klick auf die Kategorie zeigt: Artikel
     * mit dieser Kategorie oder dieser Unterkategorie. Ein Aufsummieren der
     * Unterkategorien waere falsch — ein Artikel in einer Unterkategorie traegt
     * immer auch die Oberkategorie und wuerde dort sonst doppelt zaehlen.
     *
     * @return Collection<int, array{id: int, name: string, meta: string, anzahl: int, unterkategorie: bool}>
     */
    private function categories(): Collection
    {
        // Die Zaehler beruecksichtigen Suche, Status und Tag — alles ausser der
        // Kategorie selbst. Sonst stuende in der Leiste eine Vier, waehrend der
        // Klick darauf eine leere Tabelle zeigt.
        $gefiltert = fn (Builder $query): Builder => $query
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->tagId !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereKey($this->tagId),
            ));

        $kategorien = Category::query()
            ->with(['parent', 'children'])
            ->withCount([
                'products' => $gefiltert,
                'subcategoryProducts' => $gefiltert,
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Category $category): string => $category->path());

        return $kategorien
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'meta' => $category->parent
                    ? $category->parent->name
                    : trans_choice(':count Unterkategorie|:count Unterkategorien', $category->children->count()),
                'anzahl' => $category->products_count + $category->subcategory_products_count,
                'unterkategorie' => $category->parent !== null,
            ])
            ->values();
    }

    /**
     * Artikel insgesamt, auf derselben Grundlage wie die Kategoriezaehler —
     * also ohne die Einschraenkung auf eine Kategorie.
     */
    private function categoryTotal(): int
    {
        return Product::query()
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->tagId !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereKey($this->tagId),
            ))
            ->count();
    }
}
