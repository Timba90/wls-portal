<?php

namespace App\Livewire\CustomFields;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Verwaltung der benutzerdefinierten Felder.
 */
#[Layout('components.layouts.app')]
#[Title('Benutzerdefinierte Felder')]
class CustomFieldDefinitionList extends Component
{
    #[Url(as: 'bereich', except: '')]
    public string $entityFilter = '';

    public bool $showForm = false;

    public ?int $editingDefinitionId = null;

    public string $entity_type = CustomFieldEntity::Customer->value;

    public string $key = '';

    public string $name = '';

    public string $type = CustomFieldType::Text->value;

    public bool $is_required = false;

    public string $default_value = '';

    public string $optionsInput = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->entity_type = $this->entityFilter !== '' ? $this->entityFilter : CustomFieldEntity::Customer->value;
        $this->showForm = true;
    }

    public function edit(int $definitionId): void
    {
        $definition = CustomFieldDefinition::query()->findOrFail($definitionId);

        $this->resetForm();
        $this->editingDefinitionId = $definition->id;
        $this->entity_type = $definition->entity_type->value;
        $this->key = $definition->key;
        $this->name = $definition->name;
        $this->type = $definition->type->value;
        $this->is_required = $definition->is_required;
        $this->default_value = (string) $definition->default_value;
        $this->optionsInput = implode("\n", $definition->options ?? []);
        $this->sort_order = $definition->sort_order;
        $this->is_active = $definition->is_active;
        $this->showForm = true;
    }

    /**
     * Leitet den Schluessel aus dem Namen ab, solange er nicht von Hand
     * gesetzt wurde.
     */
    public function updatedName(string $value): void
    {
        if ($this->editingDefinitionId) {
            return;
        }

        $this->key = Str::snake(Str::ascii($value));
    }

    public function requiresOptions(): bool
    {
        return CustomFieldType::from($this->type)->requiresOptions();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'entity_type' => ['required', Rule::in(CustomFieldEntity::values())],
            'key' => [
                'required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'key')
                    ->where('entity_type', $this->entity_type)
                    ->ignore($this->editingDefinitionId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(CustomFieldType::values())],
            'is_required' => ['boolean'],
            'default_value' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], [
            'key.regex' => 'Der Schlüssel darf nur Kleinbuchstaben, Zahlen und Unterstriche enthalten und muss mit einem Buchstaben beginnen.',
        ], attributes: [
            'entity_type' => 'Bereich',
            'key' => 'Schlüssel',
            'name' => 'Name',
            'type' => 'Typ',
            'default_value' => 'Standardwert',
            'sort_order' => 'Sortierung',
        ]);

        $options = $this->parseOptions();

        if ($this->requiresOptions() && $options === []) {
            $this->addError('optionsInput', 'Für Auswahlfelder wird mindestens eine Option benötigt.');

            return;
        }

        $attributes = [
            ...$validated,
            'default_value' => $validated['default_value'] ?: null,
            'options' => $options ?: null,
        ];

        if ($this->editingDefinitionId) {
            CustomFieldDefinition::query()->findOrFail($this->editingDefinitionId)->update($attributes);
        } else {
            CustomFieldDefinition::query()->create($attributes);
        }

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('feld-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.custom-fields.custom-field-definition-list', [
            'definitions' => $this->definitions(),
            'entityOptions' => CustomFieldEntity::options(),
            'typeOptions' => CustomFieldType::options(),
        ]);
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    private function definitions(): Collection
    {
        return CustomFieldDefinition::query()
            ->when($this->entityFilter !== '', fn ($query) => $query->where('entity_type', $this->entityFilter))
            ->withCount('values')
            ->orderBy('entity_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function parseOptions(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $this->optionsInput) ?: [])
            ->map(fn (string $option): string => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resetForm(): void
    {
        $this->reset(
            'editingDefinitionId', 'entity_type', 'key', 'name', 'type',
            'is_required', 'default_value', 'optionsInput', 'sort_order', 'is_active',
        );

        $this->resetValidation();
    }
}
