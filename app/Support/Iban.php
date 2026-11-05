<?php

namespace App\Support;

/**
 * Pruefung und Normalisierung einer IBAN nach ISO 13616 (Modulo 97).
 */
final class Iban
{
    public static function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $iban) ?? '');
    }

    public static function isValid(string $iban): bool
    {
        $iban = self::normalize($iban);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        if (str_starts_with($iban, 'DE') && strlen($iban) !== 22) {
            return false;
        }

        $umgestellt = substr($iban, 4).substr($iban, 0, 4);
        $ziffern = preg_replace_callback('/[A-Z]/', fn (array $treffer): string => (string) (ord($treffer[0]) - 55), $umgestellt);

        $rest = 0;
        foreach (str_split((string) $ziffern, 7) as $block) {
            $rest = (int) ($rest.$block) % 97;
        }

        return $rest === 1;
    }

    /**
     * Lesbare Schreibweise in Vierergruppen.
     */
    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }
}
