<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ProjectMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Meilenstein innerhalb eines Projekts.
 */
#[Fillable(['name', 'note', 'status', 'due_date', 'sort_order'])]
class ProjectMilestone extends Model
{
    /** @use HasFactory<ProjectMilestoneFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Verbleibende Tage bis zum Termin. Negativ, wenn er verstrichen ist.
     */
    public function daysUntilDue(): ?int
    {
        // Von heute zum Termin — die Gegenrichtung dreht das Vorzeichen um.
        return $this->due_date === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), absolute: false);
    }

    public function isOverdue(): bool
    {
        $tage = $this->daysUntilDue();

        return $tage !== null && $tage < 0 && ! $this->status->countsAsSettled();
    }

    /**
     * Termin in Worten: „in 5 Tagen", „heute", „12 Tage überfällig".
     */
    public function dueLabel(): ?string
    {
        $tage = $this->daysUntilDue();

        if ($tage === null) {
            return null;
        }

        if ($this->status->countsAsSettled()) {
            return null;
        }

        return match (true) {
            $tage === 0 => 'heute',
            $tage === 1 => 'morgen',
            $tage > 1 => "in {$tage} Tagen",
            $tage === -1 => '1 Tag überfällig',
            default => abs($tage).' Tage überfällig',
        };
    }

    /**
     * @param  Builder<ProjectMilestone>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [MilestoneStatus::Open->value, MilestoneStatus::InProgress->value]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'due_date' => 'date',
        ];
    }
}
