<?php

namespace App\Models;

use App\Enums\RegistrarProvider;
use App\Exceptions\ReadOnlyRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine ausgefuehrte DNS-Aenderung.
 *
 * Das Protokoll des einen schreibenden Zugriffs, den dieses Portal hat. Wie ein
 * Audit-Eintrag unveraenderlich: wer die Spur nachtraeglich glaetten kann, hat
 * keine.
 */
#[Fillable([
    'domain_id',
    'domain_name',
    'provider',
    'operation',
    'record_name',
    'record_type',
    'before',
    'after',
    'fingerprint',
    'verified',
    'note',
    'user_id',
    'applied_at',
])]
class DnsChange extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new ReadOnlyRecordException('Eine protokollierte DNS-Änderung kann nicht verändert werden.');
        });

        static::deleting(function (): void {
            throw new ReadOnlyRecordException('Eine protokollierte DNS-Änderung kann nicht gelöscht werden.');
        });
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Die Aenderungen, die der Anbieter danach nicht so zeigte wie bestellt.
     *
     * Der Stapel, den man sich ansieht: der Aufruf ging durch, das Ergebnis
     * passt nicht.
     *
     * @param  Builder<DnsChange>  $query
     */
    public function scopeUnverified(Builder $query): void
    {
        $query->where('verified', false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => RegistrarProvider::class,
            'verified' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }
}
