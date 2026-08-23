<?php

namespace App\Support;

/**
 * Bringt Werte aus der Aenderungshistorie in eine lesbare Form.
 */
final class AuditValueFormatter
{
    public static function format(mixed $value): string
    {
        return match (true) {
            is_null($value) => '—',
            is_bool($value) => $value ? 'Ja' : 'Nein',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—',
            $value === '' => '—',
            default => (string) $value,
        };
    }
}
