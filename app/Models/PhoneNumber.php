<?php

namespace App\Models;

use App\Enums\ContactChannelType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Telefonnummer eines Privatkunden oder Ansprechpartners.
 */
#[Fillable(['number', 'type', 'is_primary', 'sort_order'])]
class PhoneNumber extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactChannelType::class,
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
