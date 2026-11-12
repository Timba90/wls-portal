<?php

namespace App\Support\Lexoffice;

use Illuminate\Support\Str;

/**
 * Vergleichsform fuer Namen und Bezeichnungen aus Lexoffice.
 *
 * Gross- und Kleinschreibung, Satzzeichen und Leerraum spielen keine Rolle,
 * eine fuehrende Anrede ebenso wenig: Lexoffice schreibt Privatkunden als
 * „Frau Kristin Duvinage", das Portal als „Kristin Duvinage".
 */
final class MatchText
{
    private const array SALUTATIONS = ['herr', 'frau', 'firma', 'divers'];

    public static function normalize(?string $text): string
    {
        $text = Str::of((string) $text)
            ->lower()
            ->replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'])
            ->replaceMatches('/[^\p{L}\p{N}&]+/u', ' ')
            ->squish()
            ->toString();

        foreach (self::SALUTATIONS as $salutation) {
            if (str_starts_with($text, $salutation.' ')) {
                return substr($text, strlen($salutation) + 1);
            }
        }

        return $text;
    }
}
