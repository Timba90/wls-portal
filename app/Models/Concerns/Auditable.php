<?php

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Protokolliert Aenderungen eines Models in der Aenderungshistorie.
 *
 * Erfasst Benutzer, Zeitpunkt, Aktion, Model, Datensatz-ID sowie alte und neue
 * Werte. Nutzende Models koennen ueber `auditExcluded()` Felder ausnehmen und
 * ueber `auditLabels()` sprechende Feldbezeichnungen liefern.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->recordAudit(AuditEvent::Created, [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $neu = $model->auditableAttributes($model->getChanges());

            if ($neu === []) {
                return;
            }

            $alt = collect($model->getOriginal())
                ->only(array_keys($neu))
                ->all();

            $model->recordAudit($model->resolveAuditEvent($neu), $model->auditableAttributes($alt), $neu);
        });

        static::deleted(function (Model $model): void {
            $model->recordAudit(AuditEvent::Deleted, $model->auditableAttributes($model->getOriginal()), []);
        });
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Felder, die nicht protokolliert werden.
     *
     * @return array<int, string>
     */
    public function auditExcluded(): array
    {
        return ['created_at', 'updated_at', 'remember_token', 'password'];
    }

    /**
     * Sprechende Bezeichnungen fuer die Historie.
     *
     * @return array<string, string>
     */
    public function auditLabels(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $newValues
     */
    public function recordAudit(
        AuditEvent $event,
        array $values = [],
        array $newValues = [],
        ?string $description = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $values ?: null,
            'new_values' => $newValues ?: null,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Entfernt ausgenommene Felder und normalisiert Werte fuer die Ablage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        return collect($attributes)
            ->except($this->auditExcluded())
            ->map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value)
            ->all();
    }

    /**
     * Erkennt Archivierung und Reaktivierung als eigene Ereignisse.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function resolveAuditEvent(array $changes): AuditEvent
    {
        if (! array_key_exists('archived_at', $changes)) {
            return AuditEvent::Updated;
        }

        return is_null($changes['archived_at']) ? AuditEvent::Restored : AuditEvent::Archived;
    }
}
