<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Dateiversion eines Dokuments.
 *
 * Eine neue Datei ersetzt die alte Version niemals physisch.
 */
#[Fillable([
    'version',
    'disk',
    'path',
    'original_filename',
    'mime_type',
    'size_bytes',
    'checksum',
    'uploaded_by',
])]
class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Darf die Datei im Browser eingebettet angezeigt werden?
     *
     * SVG zaehlt zwar als Bild, kann aber Skripte tragen. Eingebettet liefe
     * das im Ursprung der Anwendung. Die Vorschau setzt zwar eine CSP mit
     * `default-src 'none'`, die genau das blockiert — aber ein Dateiformat
     * auszuliefern, dessen Ungefaehrlichkeit allein an einem Kopfzeilenfeld
     * haengt, ist unnoetig. SVG wird deshalb zum Download angeboten.
     */
    public function isPreviewable(): bool
    {
        if ($this->isScriptableImage()) {
            return false;
        }

        return $this->isImage() || $this->isPdf();
    }

    /**
     * Bildformate, die ausfuehrbaren Inhalt tragen koennen.
     */
    public function isScriptableImage(): bool
    {
        return in_array($this->mime_type, ['image/svg+xml', 'image/svg'], strict: true);
    }

    /**
     * Menschenlesbare Dateigroesse.
     */
    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        foreach (['B', 'KB', 'MB', 'GB'] as $einheit) {
            if ($bytes < 1024 || $einheit === 'GB') {
                return number_format($bytes, $einheit === 'B' ? 0 : 1, ',', '.').' '.$einheit;
            }

            $bytes /= 1024;
        }

        return '0 B';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
