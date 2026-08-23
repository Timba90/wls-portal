<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Exceptions\ReadOnlyRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eintrag der Aenderungshistorie.
 *
 * Eintraege sind unveraenderlich und ueber die Anwendung nicht loeschbar.
 */
#[Fillable([
    'user_id',
    'auditable_type',
    'auditable_id',
    'event',
    'old_values',
    'new_values',
    'description',
    'ip_address',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new ReadOnlyRecordException('Audit-Einträge können nicht verändert werden.');
        });

        static::deleting(function (): void {
            throw new ReadOnlyRecordException('Audit-Einträge können nicht gelöscht werden.');
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Geaenderte Felder als Paare aus altem und neuem Wert.
     *
     * @return array<string, array{alt: mixed, neu: mixed}>
     */
    public function changes(): array
    {
        $alt = $this->old_values ?? [];
        $neu = $this->new_values ?? [];

        return collect(array_keys($alt + $neu))
            ->mapWithKeys(fn (string $feld): array => [$feld => [
                'alt' => $alt[$feld] ?? null,
                'neu' => $neu[$feld] ?? null,
            ]])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
