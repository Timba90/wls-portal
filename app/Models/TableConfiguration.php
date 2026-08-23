<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Global gueltige Darstellung einer Listentabelle.
 *
 * `columns` haelt je Spaltenschluessel Sichtbarkeit, Position und Breite:
 * [['key' => 'customer_number', 'visible' => true, 'width' => 140], ...]
 */
#[Fillable(['table_key', 'columns'])]
class TableConfiguration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }
}
