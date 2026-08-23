<?php

namespace App\Models;

use Database\Factories\ContactRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Frei definierbare Ansprechpartnerrolle, etwa Geschäftsführung oder Technik.
 */
#[Fillable(['name', 'description', 'sort_order', 'is_active'])]
class ContactRole extends Model
{
    /** @use HasFactory<ContactRoleFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<ContactAssignment, $this>
     */
    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(ContactAssignment::class, 'contact_assignment_role');
    }

    /**
     * @return HasMany<ContactDeputy, $this>
     */
    public function deputies(): HasMany
    {
        return $this->hasMany(ContactDeputy::class);
    }

    /**
     * @param  Builder<ContactRole>  $query
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
