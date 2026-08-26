<?php

namespace App\Models;

use App\Enums\RegistrarProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zugangsdaten einer Schnittstelle.
 *
 * Bewusst ohne `Auditable`: die Aenderungshistorie haelt alte und neue Werte
 * fest, und ein Kennwort gehoert dort nicht hinein. Wer wann zuletzt etwas
 * geaendert hat, steht in `updated_by` und `updated_at` — das genuegt.
 */
#[Fillable([
    'provider',
    'credentials',
    'updated_by',
])]
class IntegrationCredential extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Die hinterlegten Werte eines Anbieters; leer, wenn nichts hinterlegt ist.
     *
     * @return array<string, string>
     */
    public static function valuesFor(RegistrarProvider $provider): array
    {
        $eintrag = static::query()->where('provider', $provider->value)->first();

        /** @var array<string, string> */
        return $eintrag?->credentials ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Laravel verschluesselt beim Schreiben und entschluesselt beim
            // Lesen; in der Datenbank steht nie Klartext.
            'credentials' => 'encrypted:array',
        ];
    }
}
