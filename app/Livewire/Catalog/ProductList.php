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
            'tags' => $this->tags(),
            'statusOptions' => CatalogStatus::options(),
        ]);
    }

    protected function tableKey(): string
    {
        return 'products';
    }

    /**
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'name' => ['label' => 'Name', 'fixed' => true],
            'internal_name' => ['label' => 'Interne Bezeichnung'],
            'category' => ['label' => 'Kategorie', 'sortable' => false],
            'tags' => ['label' => 'Tags', 'sortable' => false],
            'variants_count' => ['label' => 'Varianten', 'sortable' => false, 'width' => 120],
            'default_purchase_price_cents' => ['label' => 'Einkauf', 'width' => 130],
            'default_sales_price_cents' => ['label' => 'Verkauf', 'width' => 130],
            'margin' => ['label' => 'Marge', 'sortable' => false, 'width' => 150],
            'interval' => ['label' => 'Intervall', 'sortable' => false, 'width' => 150],
            'status' => ['label' => 'Status', 'width' => 120],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    private function products(): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'subcategory', 'tags'])
            ->withCount('variants')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('internal_name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->categoryId !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->where('category_id', $this->categoryId)
                    ->orWhere('subcategory_id', $this->categoryId),
            ))
            ->when($this->tagId !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereKey($this->tagId),
            ))
            ->orderBy($this->sortColumn(), $this->sort['direction'])
            ->paginate(25);
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
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categories(): Collection
    {
        return Category::query()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Category $category): string => $category->path())
            ->map(fn (Category $category): array => ['id' => $category->id, 'label' => $category->path()])
            ->values();
    }

    /**
     * @return Collection<int, Tag>
     */
    private function tags(): Collection
    {
        return Tag::query()->orderBy('name')->get();
    }
}
