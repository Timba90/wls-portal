<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Geldbetrag in Cent.
 *
 * Geld wird in dieser Anwendung ausschliesslich als Integer in Cent gehalten —
 * niemals als float. Waehrung ist durchgaengig EUR.
 */
final readonly class Money implements Stringable
{
    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Erzeugt einen Betrag aus einer Eingabe in Euro.
     *
     * Die Oberfläche ist deutsch, deshalb ist "1.234,56" die Regelform. Zur
     * Bequemlichkeit wird auch "1234.56" erkannt:
     *
     * - Enthält die Eingabe ein Komma, ist das Komma das Dezimaltrennzeichen
     *   und Punkte sind Tausendertrennzeichen.
     * - Ohne Komma gilt ein einzelner Punkt mit ein oder zwei nachfolgenden
     *   Ziffern als Dezimaltrennzeichen ("1234.5", "1234.56").
     * - Alle übrigen Punkte sind Tausendertrennzeichen ("1.234", "1.234.567").
     */
    public static function fromEuroInput(string|int|float|null $value): self
    {
        if ($value === null || $value === '') {
            return self::zero();
        }

        if (is_int($value)) {
            return new self($value * 100);
        }

        $normalised = is_float($value)
            ? (string) $value
            : self::normaliseDecimalSeparators(trim($value));

        if (! is_numeric($normalised)) {
            throw new InvalidArgumentException("Ungültiger Geldbetrag: {$value}");
        }

        return new self((int) round((float) $normalised * 100));
    }

    private static function normaliseDecimalSeparators(string $value): string
    {
        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        if (preg_match('/^(-?\d+)\.(\d{1,2})$/', $value, $matches) === 1) {
            return $matches[1].'.'.$matches[2];
        }

        return str_replace('.', '', $value);
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /**
     * Multipliziert den Betrag und rundet kaufmaennisch auf ganze Cent.
     */
    public function multipliedBy(float $factor): self
    {
        return new self((int) round($this->cents * $factor));
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    /**
     * Betrag in Euro — nur fuer Anzeige und Formulareingaben verwenden.
     */
    public function toEuro(): float
    {
        return $this->cents / 100;
    }

    /**
     * Deutsche Formatierung, zum Beispiel "1.234,56 €".
     */
    public function format(bool $withCurrency = true): string
    {
        $formatted = number_format($this->cents / 100, 2, ',', '.');

        return $withCurrency ? $formatted.' €' : $formatted;
    }

    /**
     * Eingabewert fuer Formularfelder, zum Beispiel "1234,56".
     */
    public function toInput(): string
    {
        return number_format($this->cents / 100, 2, ',', '');
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
