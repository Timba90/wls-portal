<?php

namespace App\Livewire\CustomFields;

use App\Actions\CustomFields\SaveCustomFieldValues;
use App\Models\CustomFieldDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Bearbeitung der benutzerdefinierten Felder eines Datensatzes.
 */
class CustomFieldsPanel extends Component
{
    public Model $record;

    public bool $readOnly = false;

    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(Model $record, bool $readOnly = false): void
    {
        $this->record = $record;
        $this->readOnly = $readOnly;
        $this->loadValues();
    }

    public function save(SaveCustomFieldValues $saveCustomFieldValues): void
    {
        $this->validate($this->rules(), attributes: $this->attributeNames());

        $saveCustomFieldValues($this->record, $this->values);

        $this->dispatch('felder-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.custom-fields.custom-fields-panel', [
            'definitions' => $this->definitions(),
        ]);
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    private function definitions(): Collection
    {
        return $this->record->customFieldDefinitions();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return $this->definitions()
            ->mapWithKeys(fn (CustomFieldDefinition $definition): array => [
                "values.{$definition->key}" => [
                    $definition->is_required ? 'required' : 'nullable',
                    ...$definition->type->validationRules(),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return $this->definitions()
            ->mapWithKeys(fn (CustomFieldDefinition $definition): array => [
                "values.{$definition->key}" => $definition->name,
            ])
            ->all();
    }

    private function loadValues(): void
    {
        $gespeichert = $this->record->customFieldData();

        $this->values = $this->definitions()
            ->mapWithKeys(fn (CustomFieldDefinition $definition): array => [
                $definition->key => $gespeichert[$definition->key]
                    ?? ($definition->type->isMultiValue() ? [] : $definition->default_value),
            ])
            ->all();
    }
}
