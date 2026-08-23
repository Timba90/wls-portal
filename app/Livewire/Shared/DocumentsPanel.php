<?php

namespace App\Livewire\Shared;

use App\Actions\Documents\UploadDocument;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Dokumentbereich fuer Kunden, Ansprechpartner und Kundenleistungen.
 *
 * Dateien liegen in privatem Object Storage; Download und Vorschau laufen
 * ausschliesslich ueber die Anwendung.
 */
class DocumentsPanel extends Component
{
    use WithFileUploads;

    public Model $documentable;

    public bool $showUploadForm = false;

    public ?int $newVersionForDocumentId = null;

    public string $name = '';

    public string $description = '';

    #[Validate]
    public mixed $file = null;

    public function mount(Model $documentable): void
    {
        $this->documentable = $documentable;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.((int) config('portal.documents.max_size_mb') * 1024)],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return ['file' => 'Datei', 'name' => 'Name', 'description' => 'Beschreibung'];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showUploadForm = true;
    }

    public function addVersion(int $documentId): void
    {
        $document = $this->documentable->documents()->findOrFail($documentId);

        $this->resetForm();
        $this->newVersionForDocumentId = $document->id;
        $this->name = $document->name;
        $this->description = (string) $document->description;
        $this->showUploadForm = true;
    }

    public function upload(UploadDocument $uploadDocument): void
    {
        $this->validate();

        $uploadDocument(
            documentable: $this->documentable,
            file: $this->file,
            name: $this->name ?: null,
            description: $this->description ?: null,
            user: auth()->user(),
            document: $this->newVersionForDocumentId
                ? $this->documentable->documents()->findOrFail($this->newVersionForDocumentId)
                : null,
        );

        $this->showUploadForm = false;
        $this->resetForm();

        $this->dispatch('dokument-gespeichert');
    }

    public function archive(int $documentId): void
    {
        $this->documentable->documents()
            ->findOrFail($documentId)
            ->forceFill(['archived_at' => now()])
            ->save();

        $this->dispatch('dokument-archiviert');
    }

    public function restore(int $documentId): void
    {
        $this->documentable->documents()
            ->findOrFail($documentId)
            ->forceFill(['archived_at' => null])
            ->save();

        $this->dispatch('dokument-reaktiviert');
    }

    public function render(): View
    {
        return view('livewire.shared.documents-panel', [
            'documents' => $this->documents(),
            'maxSizeMb' => (int) config('portal.documents.max_size_mb'),
        ]);
    }

    /**
     * @return Collection<int, Document>
     */
    private function documents(): Collection
    {
        return $this->documentable
            ->documents()
            ->with(['currentVersion.uploader', 'versions.uploader', 'uploader'])
            ->get();
    }

    private function resetForm(): void
    {
        if ($this->file instanceof TemporaryUploadedFile) {
            $this->file->delete();
        }

        $this->reset('newVersionForDocumentId', 'name', 'description', 'file');
        $this->resetValidation();
    }
}
