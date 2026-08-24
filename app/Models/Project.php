<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Exceptions\ImmutableAttributeException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasTags;
use App\Support\Money;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Ein Projekt eines Kunden.
 *
 * Die Projektnummer wird beim Anlegen vergeben und ist danach unveraenderlich,
 * genau wie die Kundennummer.
 */
#[Fillable([
    'name',
    'description',
    'customer_id',
    'project_type_id',
    'responsible_user_id',
    'status',
    'start_date',
    'deadline',
    'risk_note',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use Auditable, HasCustomFields, HasDocuments, HasFactory, HasNotes, HasTags;

    protected static function booted(): void
    {
        static::updating(function (self $project): void {
            if ($project->isDirty('project_number')) {
                throw new ImmutableAttributeException(
                    'Die Projektnummer kann nach der Erstellung nicht mehr geändert werden.'
                );
            }
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<ProjectType, $this>
     */
    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)
            ->orderBy('sort_order')
            ->orderBy('due_date');
    }

    /**
     * @return HasMany<ProjectPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(ProjectPosition::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isArchived(): bool
    {
        return $this->status === ProjectStatus::Archived;
    }

    /**
     * Kuerzel fuer die Kachel im Kopf und in der Liste.
     */
    public function initials(): string
    {
        return Str::upper(Str::substr(trim($this->name), 0, 2));
    }

    /**
     * Fortschritt in Prozent, aus den Meilensteinen abgeleitet.
     *
     * `null`, wenn es keine Meilensteine gibt — dann ist ein Prozentwert eine
     * Behauptung ohne Grundlage, und die Oberflaeche zeigt statt eines Balkens
     * einen Strich.
     */
    public function progressPercentage(): ?int
    {
        $gesamt = $this->milestones->count();

        if ($gesamt === 0) {
            return null;
        }

        $erledigt = $this->milestones
            ->filter(fn (ProjectMilestone $meilenstein): bool => $meilenstein->status->countsAsSettled())
            ->count();

        return (int) round($erledigt / $gesamt * 100);
    }

    /**
     * Einmaliges Projektvolumen: Summe aller einmaligen Positionen.
     */
    public function oneTimeVolume(): Money
    {
        return $this->sumPositions(fn (ProjectPosition $position): bool => $position->isOneTime());
    }

    /**
     * Wiederkehrender Anteil, getrennt ausgewiesen — er gehoert nicht in das
     * einmalige Volumen, weil er sich nicht auf denselben Zeitraum bezieht.
     */
    public function recurringVolume(): Money
    {
        return $this->sumPositions(fn (ProjectPosition $position): bool => ! $position->isOneTime());
    }

    /**
     * Verbleibende Tage bis zur Deadline. Negativ, wenn sie ueberschritten ist.
     */
    public function daysUntilDeadline(): ?int
    {
        // Richtung beachten: von heute zur Deadline, nicht umgekehrt. Sonst
        // waeren verbleibende Tage negativ und ueberschrittene positiv.
        return $this->deadline === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->deadline->startOfDay(), absolute: false);
    }

    public function isOverdue(): bool
    {
        $tage = $this->daysUntilDeadline();

        return $tage !== null && $tage < 0 && $this->status->isOpen();
    }

    /**
     * @param  callable(ProjectPosition): bool  $filter
     */
    private function sumPositions(callable $filter): Money
    {
        return $this->positions
            ->filter($filter)
            ->reduce(
                fn (Money $summe, ProjectPosition $position): Money => $summe->plus($position->total()),
                Money::zero(),
            );
    }

    /**
     * Projekte, die noch laufen: geplant, laufend oder pausiert.
     *
     * Bewusst nicht `active()` genannt. Im ganzen Projekt heisst `active()`
     * „Status ist Aktiv" (Kunde, Artikel, Kundenleistung); ein Projekt hat
     * dagegen drei offene Status, und ein abgebrochenes waere unter dem Namen
     * `active()` beinahe zwangslaeufig irgendwann mitgezaehlt worden.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::openStatusValues());
    }

    /**
     * Alles ausser dem Archiv — auch abgeschlossen und abgebrochen.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->where('status', '!=', ProjectStatus::Archived);
    }

    /**
     * Die Werte der offenen Status, fuer Abfragen ausserhalb des Scopes.
     *
     * @return array<int, string>
     */
    public static function openStatusValues(): array
    {
        return array_values(array_map(
            fn (ProjectStatus $status): string => $status->value,
            array_filter(ProjectStatus::cases(), fn (ProjectStatus $status): bool => $status->isOpen()),
        ));
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->where('status', ProjectStatus::Archived);
    }

    /**
     * @return array<string, string>
     */
    public function auditLabels(): array
    {
        return [
            'project_number' => 'Projektnummer',
            'name' => 'Name',
            'description' => 'Beschreibung',
            'customer_id' => 'Kunde',
            'project_type_id' => 'Projekttyp',
            'responsible_user_id' => 'Verantwortlich',
            'status' => 'Status',
            'start_date' => 'Beginn',
            'deadline' => 'Deadline',
            'risk_note' => 'Risiko',
            'archived_at' => 'Archiviert am',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'deadline' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
