<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Macht ein Model taggbar.
 *
 * Die Beziehung ist polymorph, damit Tags ohne Migration auch fuer spaetere
 * Bereiche wie Projekte oder Domains nutzbar bleiben.
 */
trait HasTags
{
    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('name');
    }
}
