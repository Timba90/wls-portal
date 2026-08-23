<?php

namespace App\Livewire\Catalog;

use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Verwaltung der Tags.
 *
 * Tags sind generisch und werden fuer Kunden, Ansprechpartner, Katalogartikel
 * und Kundenleistungen verwendet.
 */
#[Layout('components.layouts.app')]
#[Title('Tags')]
class TagList extends Component
{
    /** @var array<int, string> */
    public const COLORS = ['gray', 'blue', 'green', 'amber', 'red', 'purple'];

    public bool $showForm = false;

    public ?int $editingTagId = null;

    public string $name = '';

    public string $color = 'gray';

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $tagId): void
    {
        $tag = Tag::query()->findOrFail($tagId);

        $this->resetForm();
        $this->editingTagId = $tag->id;
        $this->name = $tag->name;
        $this->color = $tag->color;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')->ignore($this->editingTagId)],
            'color' => ['required', Rule::in(self::COLORS)],
        ], attributes: ['name' => 'Name', 'color' => 'Farbe']);

        if ($this->editingTagId) {
            Tag::query()->findOrFail($this->editingTagId)->update($validated);
        } else {
            Tag::query()->create($validated);
        }

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('tag-gespeichert');
    }

    public function render(): View
    {
        return view('livewire.catalog.tag-list', [
            'tags' => $this->tags(),
            'colorOptions' => array_map(
                fn (string $color): array => ['label' => $this->colorLabel($color), 'value' => $color],
                self::COLORS,
            ),
        ]);
    }

    /**
     * @return Collection<int, Tag>
     */
    private function tags(): Collection
    {
        return Tag::query()
            ->withCount(['customers', 'contacts', 'products'])
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
        $this->reset('editingTagId', 'name', 'color');
        $this->resetValidation();
    }
}
