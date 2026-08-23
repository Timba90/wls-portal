<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Legt ein Dokument an oder haengt eine neue Version an ein bestehendes.
 *
 * Die Datei landet in privatem Object Storage; eine neue Version ersetzt die
 * alte niemals physisch.
 */
class UploadDocument
{
    public function __construct(private readonly GuardUploadedFile $guardUploadedFile) {}

    public function __invoke(
        Model $documentable,
        UploadedFile $file,
        ?string $name = null,
        ?string $description = null,
        ?User $user = null,
        ?Document $document = null,
    ): Document {
        ($this->guardUploadedFile)($file);

        return DB::transaction(function () use ($documentable, $file, $name, $description, $user, $document): Document {
            $document ??= $documentable->documents()->create([
                'name' => $name ?: $file->getClientOriginalName(),
                'description' => $description,
                'uploaded_by' => $user?->getKey(),
            ]);

            $version = ($document->versions()->max('version') ?? 0) + 1;

            $path = $file->storeAs(
                $this->directoryFor($document),
                Str::uuid()->toString().'.'.$this->extensionFor($file),
                ['disk' => $this->disk()],
            );

            $document->versions()->create([
                'version' => $version,
                'disk' => $this->disk(),
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
                'uploaded_by' => $user?->getKey(),
            ]);

            $document->unsetRelation('versions')->unsetRelation('currentVersion');

            return $document;
        });
    }

    /**
     * Ablagepfad je Dokument — der Dateiname selbst ist zufaellig, damit
     * Originalnamen keine Rueckschluesse im Speicher erlauben.
     */
    private function directoryFor(Document $document): string
    {
        return 'dokumente/'.Str::of($document->documentable_type)->afterLast('\\')->kebab()
            .'/'.$document->documentable_id;
    }

    private function extensionFor(UploadedFile $file): string
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? $extension : 'bin';
    }

    private function disk(): string
    {
        return (string) config('portal.documents.disk');
    }
}
