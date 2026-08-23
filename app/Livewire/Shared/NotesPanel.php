<?php

namespace App\Livewire\Shared;

use App\Actions\Notes\SaveNote;
use App\Enums\NoteCategory;
use App\Models\Note;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Notizbereich fuer Kunden, Ansprechpartner und Kundenleistungen.
 */
class NotesPanel extends Component
{
    public Model $notable;

    public bool $showForm = false;

    public ?int $editingNoteId = null;

    public string $category = NoteCategory::General->value;

    public string $body = '';

    public string $filterCategory = '';

    public function mount(Model $notable): void
    {
        $this->notable = $notable;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $noteId): void
    {
        $note = $this->notable->notes()->findOrFail($noteId);

        $this->resetForm();
        $this->editingNoteId = $note->id;
        $this->category = $note->category->value;
        $this->body = $note->body;
        $this->showForm = true;
    }

    public function save(SaveNote $saveNote): void
    {
        $this->validate([
            'category' => ['required', Rule::in(NoteCategory::values())],
            'body' => ['required', 'string', 'max:10000'],
        ], attributes: ['category' => 'Kategorie', 'body' => 'Text']);

        $saveNote(
            notable: $this->notable,
            category: NoteCategory::from($this->category),
            body: $this->body,
            user: auth()->user(),
            note: $this->editingNoteId
                ? $this->notable->notes()->findOrFail($this->editingNoteId)
                : null,
        );

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('notiz-gespeichert');
    }

    public function delete(int $noteId): void
    {
        $this->notable->notes()->findOrFail($noteId)->delete();

        $this->dispatch('notiz-geloescht');
    }

    public function render(): View
    {
        return view('livewire.shared.notes-panel', [
            'notes' => $this->notes(),
            'categoryOptions' => NoteCategory::options(),
        ]);
    }

    /**
     * @return Collection<int, Note>
     */
    private function notes(): Collection
    {
        return $this->notable
            ->notes()
            ->with('user')
            ->when($this->filterCategory !== '', fn ($query) => $query->where('category', $this->filterCategory))
            ->get();
    }

    private function resetForm(): void
    {
        $this->reset('editingNoteId', 'category', 'body');
        $this->resetValidation();
    }
}
