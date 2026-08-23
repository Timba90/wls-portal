<?php

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use Database\Factories\CustomFieldDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Definition eines benutzerdefinierten Feldes.
 */
#[Fillable([
    'entity_type',
    'key',
    'name',
    'type',
    'is_required',
    'default_value',
    'options',
    'sort_order',
    'is_active',
    'visibility_condition',
])]
class CustomFieldDefinition extends Model
{
    /** @use HasFactory<CustomFieldDefinitionFactory> */
    use HasFactory;

    /**
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * Optionen als Auswahlliste fuer die Oberflaeche.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function optionList(): array
    {
        return collect($this->options ?? [])
            ->map(fn (string $option): array => ['label' => $option, 'value' => $option])
            ->all();
    }

    /**
     * @param  Builder<CustomFieldDefinition>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<CustomFieldDefinition>  $query
     */
    public function scopeForEntity(Builder $query, CustomFieldEntity $entity): void
    {
        $query->where('entity_type', $entity);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => CustomFieldEntity::class,
            'type' => CustomFieldType::class,
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'array',
            'visibility_condition' => 'array',
        ];
    }
}
