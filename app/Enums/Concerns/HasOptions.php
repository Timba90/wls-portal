<?php

namespace App\Enums\Concerns;

/**
 * Wandelt die Fälle eines Enums in Optionen für TallStackUI-Selects um.
 *
 * Erwartet, dass das Enum eine `label(): string`-Methode besitzt.
 */
trait HasOptions
{
    /**
     * @param  array<int, static>|null  $cases
     * @return array<int, array{label: string, value: string}>
     */
    public static function options(?array $cases = null): array
    {
        return array_map(
            fn (self $case): array => ['label' => $case->label(), 'value' => $case->value],
            $cases ?? self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
