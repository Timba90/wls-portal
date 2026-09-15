<?php

namespace App\Models;

use App\Enums\RegistrarProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein Bestandsabgleich mit einem Registrar.
 *
 * Bewusst nicht auditierbar: die Aenderungshistorie haelt fest, was Menschen
 * an Daten tun. Ein Abgleich ist ein Betriebsvorgang und fuehrt hier sein
 * eigenes Protokoll.
 */
#[Fillable([
    'provider',
    'trigger',
    'started_at',
    'finished_at',
    'domains_new',
    'domains_updated',
    'certificates_new',
    'certificates_updated',
    'skipped',
    'error',
])]
class RegistrarSync extends Model
{
    /**
     * Der jeweils letzte Lauf je Anbieter.
     *
     * @param  Builder<RegistrarSync>  $query
     */
    #[Scope]
    protected function latestPerProvider(Builder $query): void
    {
        $query->whereIn('id', static::query()->selectRaw('max(id)')->groupBy('provider'));
    }

    public function isFailed(): bool
    {
        return filled($this->error);
    }

    /**
     * Wie viele Datensaetze der Lauf angefasst hat.
     */
    public function touched(): int
    {
        return $this->domains_new + $this->domains_updated
            + $this->certificates_new + $this->certificates_updated;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => RegistrarProvider::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
