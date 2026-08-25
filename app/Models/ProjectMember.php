<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Teammitglied eines Projekts.
 *
 * Die Rolle ist Freitext — das Projekt kennt keine Rollenverwaltung, und der
 * Entwurf zeigt Bezeichnungen wie „Projektleitung" oder „Entwicklung".
 */
#[Fillable(['user_id', 'role', 'sort_order'])]
class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
