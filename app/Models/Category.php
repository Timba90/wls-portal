<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategorie oder Unterkategorie eines Katalogartikels.
 *
 * Genau eine Hierarchiestufe: eine Unterkategorie hat eine Elternkategorie und
 * selbst keine Kinder.
 */
#[Fillable(['parent_id', 'name', 'description', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Artikel, die diese Kategorie als Unterkategorie fuehren.
     *
     * Ein Artikel traegt Kategorie und Unterkategorie in zwei Spalten; fuer die
     * Zaehlung in der Katalogleiste zaehlen beide.
     *
     * @return HasMany<Product, $this>
     */
    public function subcategoryProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'subcategory_id');
    }

    public function isSubcategory(): bool
    {
        return ! is_null($this->parent_id);
    }

    /**
     * Voller Pfad, zum Beispiel "Hosting → Managed Hosting".
     */
    public function path(): string
    {
        return $this->isSubcategory() && $this->parent
            ? "{$this->parent->name} → {$this->name}"
            : $this->name;
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
