<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Zaehlerstand einer fortlaufenden Nummer.
 *
 * Wird ausschliesslich über App\Support\Numbering\SequenceGenerator gelesen und
 * geschrieben.
 */
#[Fillable(['key', 'next_value'])]
class Sequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }
}
