<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * Legt eine Kategorie an oder aktualisiert sie.
 *
 * Es gibt genau eine Hierarchiestufe: eine Unterkategorie darf selbst keine
 * Kinder besitzen und keine weitere Unterkategorie als Elternteil haben.
 */
class SaveCategory
{
    /**
     * @param  array{name: string, parent_id?: ?int, description?: ?string, sort_order?: int, is_active?: bool}  $attributes
     */
    public function __invoke(array $attributes, ?Category $category = null): Category
    {
        $parentId = $attributes['parent_id'] ?? null;

        $this->guardAgainstDeepNesting($parentId, $category);
        $this->guardAgainstDuplicateName($attributes['name'], $parentId, $category);

        $values = [
            'parent_id' => $parentId,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'sort_order' => $attributes['sort_order'] ?? 0,
            'is_active' => $attributes['is_active'] ?? true,
        ];

        if ($category) {
            $category->update($values);

            return $category;
        }

        return Category::query()->create($values);
    }

    private function guardAgainstDeepNesting(?int $parentId, ?Category $category): void
    {
        if (is_null($parentId)) {
            return;
        }

        $parent = Category::query()->findOrFail($parentId);

        if ($parent->isSubcategory()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Es ist nur eine Unterebene vorgesehen — eine Unterkategorie kann keine weitere Ebene aufnehmen.',
            ]);
        }

        if ($category && $category->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Diese Kategorie besitzt bereits Unterkategorien und kann deshalb selbst keine werden.',
            ]);
        }

        if ($category && $category->is($parent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Eine Kategorie kann sich nicht selbst übergeordnet sein.',
            ]);
        }
    }

    /**
     * MySQL behandelt NULL in Unique-Indizes als verschieden, Hauptkategorien
     * sind vom Index deshalb nicht abgedeckt.
     */
    private function guardAgainstDuplicateName(string $name, ?int $parentId, ?Category $category): void
    {
        $exists = Category::query()
            ->where('name', $name)
            ->when(is_null($parentId),
                fn ($query) => $query->whereNull('parent_id'),
                fn ($query) => $query->where('parent_id', $parentId),
            )
            ->when($category, fn ($query) => $query->whereKeyNot($category->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Auf dieser Ebene gibt es bereits eine Kategorie mit diesem Namen.',
            ]);
        }
    }
}
