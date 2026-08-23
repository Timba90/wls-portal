<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ausgabe von Dokumentversionen.
 *
 * Die Dateien liegen in privatem Object Storage ohne oeffentliche URLs; jeder
 * Zugriff laeuft ueber diese authentifizierten Endpunkte.
 */
class DocumentController extends Controller
{
    public function download(Document $document, DocumentVersion $version): StreamedResponse
    {
        $this->guardVersionBelongsToDocument($document, $version);

        return Storage::disk($version->disk)->download(
            $version->path,
            $version->original_filename,
        );
    }

    /**
     * Vorschau im Browser — nur fuer Formate, die sich sicher darstellen
     * lassen. Alles andere wird zum Download angeboten.
     */
    public function preview(Document $document, DocumentVersion $version): Response|StreamedResponse
    {
        $this->guardVersionBelongsToDocument($document, $version);

        if (! $version->isPreviewable()) {
            return $this->download($document, $version);
        }

        return Storage::disk($version->disk)->response(
            $version->path,
            $version->original_filename,
            [
                'Content-Type' => $version->mime_type,
                'Content-Disposition' => 'inline; filename="'.addslashes($version->original_filename).'"',
                // Verhindert, dass eingebettete Inhalte externe Ressourcen laden.
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; object-src 'self'; plugin-types application/pdf;",
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function guardVersionBelongsToDocument(Document $document, DocumentVersion $version): void
    {
        abort_unless($version->document_id === $document->getKey(), 404);
    }
}
