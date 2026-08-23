<?php

namespace App\Actions\Notes;

use App\Enums\NoteCategory;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Legt eine Notiz an oder aktualisiert sie.
 */
class SaveNote
{
    public function __invoke(
        Model $notable,
        NoteCategory $category,
        string $body,
        ?User $user = null,
        ?Note $note = null,
    ): Note {
        if ($note) {
            $note->update(['category' => $category, 'body' => $body]);

            return $note;
        }

        return $notable->notes()->create([
            'category' => $category,
            'body' => $body,
            'user_id' => $user?->getKey(),
        ]);
    }
}
