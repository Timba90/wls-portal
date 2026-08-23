<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\SaveCategory;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Verwaltung der Kategorien und Unterkategorien.
 */
#[Layout('components.layouts.app')]
#[Title('Kategorien')]
class CategoryList extends Component
{
    public bool $showForm = false;

    public ?int $editingCategoryId = null;

    public string $name = '';

    public string $description = '';

    public string $parent_id = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function create(?int $parentId = null): void
    {
        $this->resetForm();
        $this->parent_id = $parentId ? (string) $parentId : '';
        $this->showForm = true;
    }

    public function edit(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $this->resetForm();
        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->description = (string) $category->description;
        $this->parent_id = (string) ($category->parent_id ?? '');
        $this->sort_order = $category->sort_order;
        $this->is_active = $category->is_active;
        $this->showForm = true;
    }

    public function save(SaveCategory $saveCategory): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], attributes: [
            'name' => 'Name',
            'description' => 'Beschreibung',
            'sort_order' => 'Sortierung',
        ]);

        $saveCategory(
            [
                ...$validated,
                'description' => $validated['description'] ?: null,
                'parent_id' => $this->parent_id !== '' ? (int) $this->parent_id : null,
            ],
            $this->editingCategoryId ? Category::query()->findOrFail($this->editingCategoryId) : null,
        );

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('kategorie-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.catalog.category-list', [
            'categories' => $this->categories(),
            'rootCategories' => $this->rootCategories(),
        ]);
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::query()
            ->roots()
            ->with('children')
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Nur Hauptkategorien kommen als Elternteil in Frage — es gibt genau eine
     * Unterebene.
     *
     * @return Collection<int, Category>
     */
    private function rootCategories(): Collection
    {
        return Category::query()
            ->roots()
            ->when($this->editingCategoryId, fn ($query) => $query->whereKeyNot($this->editingCategoryId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function resetForm(): void
    {
        $this->reset('editingCategoryId', 'name', 'description', 'parent_id', 'sort_order', 'is_active');
        $this->resetValidation();
    }
}
