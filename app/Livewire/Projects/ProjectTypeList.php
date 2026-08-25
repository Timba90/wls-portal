<?php

namespace App\Livewire\Projects;

use App\Models\ProjectType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Verwaltung der Projekttypen.
 *
 * Die Typen sind frei definierbar (§61); Webseite, Shop, Web-App, API und
 * internes Tool sind Beispiele aus der Anforderung, keine feste Liste.
 */
#[Layout('components.layouts.app')]
#[Title('Projekttypen')]
class ProjectTypeList extends Component
{
    /** @var array<int, string> */
    public const COLORS = ['gray', 'blue', 'green', 'amber', 'red', 'purple'];

    public bool $showForm = false;

    public ?int $editingTypeId = null;

    public string $name = '';

    public string $short_label = '';

    public string $color = 'gray';

    public string $sort_order = '0';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $typeId): void
    {
        $typ = ProjectType::query()->findOrFail($typeId);

        $this->resetForm();
        $this->editingTypeId = $typ->id;
        $this->name = $typ->name;
        $this->short_label = (string) $typ->short_label;
        $this->color = $typ->color;
        $this->sort_order = (string) $typ->sort_order;
        $this->is_active = $typ->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('project_types', 'name')->ignore($this->editingTypeId)],
            'short_label' => ['nullable', 'string', 'max:12'],
            'color' => ['required', Rule::in(self::COLORS)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], attributes: [
            'name' => 'Name',
            'short_label' => 'Kürzel',
            'color' => 'Farbe',
            'sort_order' => 'Sortierung',
            'is_active' => 'Aktiv',
        ]);

        $werte = [...$validated, 'short_label' => $validated['short_label'] ?: null];

        if ($this->editingTypeId) {
            ProjectType::query()->findOrFail($this->editingTypeId)->update($werte);
        } else {
            ProjectType::query()->create($werte);
        }

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('projekttyp-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.projects.project-type-list', [
            'projectTypes' => $this->projectTypes(),
            'colorOptions' => array_map(
                fn (string $color): array => ['label' => $this->colorLabel($color), 'value' => $color],
                self::COLORS,
            ),
        ]);
    }

    /**
     * @return Collection<int, ProjectType>
     */
    private function projectTypes(): Collection
    {
        return ProjectType::query()
            ->withCount('projects')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function colorLabel(string $color): string
    {
        return match ($color) {
            'gray' => 'Grau',
            'blue' => 'Blau',
            'green' => 'Grün',
            'amber' => 'Gelb',
            'red' => 'Rot',
            'purple' => 'Violett',
            default => $color,
        };
    }

    private function resetForm(): void
    {
        $this->reset('editingTypeId', 'name', 'short_label', 'color', 'sort_order', 'is_active');
        $this->resetValidation();
    }
}
