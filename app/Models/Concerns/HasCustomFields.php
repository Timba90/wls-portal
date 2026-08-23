<?php

namespace App\Models\Concerns;

use App\Enums\CustomFieldEntity;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Stattet ein Model mit benutzerdefinierten Feldern aus.
 */
trait HasCustomFields
{
    /**
     * @return MorphMany<CustomFieldValue, $this>
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'customizable');
    }

    public function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::forModel($this);
    }

    /**
     * Aktive Felddefinitionen dieses Bereichs.
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    public function customFieldDefinitions(): Collection
    {
        return CustomFieldDefinition::query()
            ->forEntity($this->customFieldEntity())
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Werte je Feldschluessel.
     *
     * @return array<string, mixed>
     */
    public function customFieldData(): array
    {
        return $this->customFieldValues
            ->mapWithKeys(fn (CustomFieldValue $value): array => [
                $value->definition->key => $value->value,
            ])
            ->all();
    }
}
