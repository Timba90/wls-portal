<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ProjectTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Frei definierbarer Projekttyp (§61).
 *
 * Webseite, Shop, Web-App, API und internes Tool sind Beispiele aus der
 * Anforderung, keine feste Liste — deshalb eine Tabelle statt eines Enums.
 */
#[Fillable(['name', 'short_label', 'color', 'sort_order', 'is_active'])]
class ProjectType extends Model
{
    /** @use HasFactory<ProjectTypeFactory> */
    use Auditable, HasFactory;

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Kuerzel fuer die Kachel; faellt auf die ersten beiden Buchstaben zurueck.
     */
    public function badge(): string
    {
        return $this->short_label ?: mb_strtoupper(mb_substr($this->name, 0, 2));
    }

    /**
     * @param  Builder<ProjectType>  $query
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
        return ['is_active' => 'boolean'];
    }
}
