<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Wert eines benutzerdefinierten Feldes an einem Datensatz.
 *
 * Der Wert liegt als JSON vor und deckt damit alle Feldtypen ab, auch die
 * Mehrfachauswahl.
 */
#[Fillable(['custom_field_definition_id', 'value'])]
class CustomFieldValue extends Model
{
    /**
     * @return BelongsTo<CustomFieldDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function customizable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Wert in lesbarer Form.
     */
    public function displayValue(): string
    {
        $value = $this->value;

        if (is_null($value) || $value === '' || $value === []) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Ja' : 'Nein';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
