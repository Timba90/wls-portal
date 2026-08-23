<?php

namespace App\Models\Concerns;

use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Macht ein Model notizfaehig.
 */
trait HasNotes
{
    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }
}
