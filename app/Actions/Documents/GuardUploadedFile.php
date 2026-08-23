<?php

namespace App\Actions\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Prueft eine hochgeladene Datei gegen Groessenlimit und Blockliste.
 *
 * Grundsaetzlich sind alle Dateitypen erlaubt; gefaehrliche ausfuehrbare
 * Endungen werden ueber eine konfigurierbare Blockliste gesperrt. Eine
 * Malware-Pruefung findet bewusst nicht statt.
 */
class GuardUploadedFile
{
    public function __invoke(UploadedFile $file): void
    {
        $this->guardAgainstOversizedFile($file);
        $this->guardAgainstBlockedExtension($file);
    }

    private function guardAgainstOversizedFile(UploadedFile $file): void
    {
        $maxBytes = (int) config('portal.documents.max_size_mb') * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            $maxMb = (int) config('portal.documents.max_size_mb');

            throw ValidationException::withMessages([
                'datei' => "Die Datei ist größer als {$maxMb} MB und kann nicht hochgeladen werden.",
            ]);
        }
    }

    private function guardAgainstBlockedExtension(UploadedFile $file): void
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        /** @var array<int, string> $blocked */
        $blocked = config('portal.documents.blocked_extensions', []);

        if (in_array($extension, $blocked, strict: true)) {
            throw ValidationException::withMessages([
                'datei' => "Dateien mit der Endung .{$extension} sind aus Sicherheitsgründen gesperrt.",
            ]);
        }
    }
}
