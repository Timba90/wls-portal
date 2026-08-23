<?php

namespace App\Actions\CustomFields;

use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Speichert die benutzerdefinierten Felder eines Datensatzes.
 *
 * Erwartet die Werte je Feldschluessel; unbekannte Schluessel werden ignoriert.
 */
class SaveCustomFieldValues
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __invoke(Model $model, array $values): void
    {
        DB::transaction(function () use ($model, $values): void {
            $definitions = CustomFieldDefinition::query()
                ->forEntity($model->customFieldEntity())
                ->active()
                ->get()
                ->keyBy('key');

            foreach ($values as $key => $value) {
                $definition = $definitions->get($key);

                if (! $definition) {
                    continue;
                }

                $model->customFieldValues()->updateOrCreate(
                    ['custom_field_definition_id' => $definition->getKey()],
                    ['value' => $this->normalise($definition, $value)],
                );
            }

            $model->unsetRelation('customFieldValues');
        });
    }

    private function normalise(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($definition->type->isMultiValue()) {
            return array_values(array_filter((array) $value, fn (mixed $item): bool => filled($item)));
        }

        return match (true) {
            $value === '' => null,
            $definition->type->value === 'boolean' => (bool) $value,
            $definition->type->value === 'number' && filled($value) => (float) $value,
            default => $value,
        };
    }
}
