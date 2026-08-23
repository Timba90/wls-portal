<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Datentyp eines benutzerdefinierten Feldes.
 */
enum CustomFieldType: string
{
    use HasOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Url = 'url';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Textbereich',
            self::Number => 'Zahl',
            self::Date => 'Datum',
            self::Boolean => 'Ja/Nein',
            self::Select => 'Auswahl',
            self::MultiSelect => 'Mehrfachauswahl',
            self::Url => 'URL',
            self::Email => 'E-Mail-Adresse',
        };
    }

    /**
     * Ob der Typ eine Optionsliste benoetigt.
     */
    public function requiresOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], strict: true);
    }

    public function isMultiValue(): bool
    {
        return $this === self::MultiSelect;
    }

    /**
     * Validierungsregeln fuer einen Wert dieses Typs.
     *
     * @return array<int, string>
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::Text, self::Textarea, self::Select => ['string'],
            self::Number => ['numeric'],
            self::Date => ['date'],
            self::Boolean => ['boolean'],
            self::MultiSelect => ['array'],
            self::Url => ['url'],
            self::Email => ['email'],
        };
    }
}
