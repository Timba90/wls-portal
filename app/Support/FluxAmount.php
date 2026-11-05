<?php

namespace App\Support;

/**
 * Betraege aus Flux kommen als Dezimalzeichenkette mit zehn Nachkommastellen
 * ("2543.0500000000"). Gerechnet wird im Portal in Cent.
 */
final class FluxAmount
{
    public static function toMoney(?string $amount): Money
    {
        if (blank($amount) || ! is_numeric($amount)) {
            return Money::zero();
        }

        return Money::fromCents((int) round((float) $amount * 100));
    }
}
