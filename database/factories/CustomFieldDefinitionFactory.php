<?php

namespace Database\Factories;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Kundennummer beim Provider', 'Vertragsnummer', 'Kündigungsfrist',
            'Serverstandort', 'Ansprechpartner beim Hersteller', 'SLA-Stufe',
        ]);

        return [
            'entity_type' => CustomFieldEntity::Customer,
            'key' => Str::snake(Str::ascii($name)),
            'name' => $name,
            'type' => CustomFieldType::Text,
            'is_required' => false,
            'default_value' => null,
            'options' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function ofType(CustomFieldType $type, ?array $options = null): static
    {
        return $this->state(fn (): array => ['type' => $type, 'options' => $options]);
    }

    public function required(): static
    {
        return $this->state(fn (): array => ['is_required' => true]);
    }

    public function forEntity(CustomFieldEntity $entity): static
    {
        return $this->state(fn (): array => ['entity_type' => $entity]);
    }
}
