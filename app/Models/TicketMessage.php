<?php

namespace App\Models;

use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eine Nachricht im Verlauf eines Tickets.
 *
 * Der Autor ist polymorph: ein interner Benutzer, ein Kundenzugang oder —
 * bei automatisch erzeugten Tickets ohne Absender — niemand.
 * Interne Notizen (`is_internal`) sieht der Kunde nie.
 */
#[Fillable(['ticket_id', 'author_type', 'author_id', 'body', 'is_internal'])]
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function isFromCustomer(): bool
    {
        return $this->author_type === (new PortalUser)->getMorphClass();
    }

    public function isFromTeam(): bool
    {
        return $this->author_type === (new User)->getMorphClass();
    }

    /**
     * Anzeigename des Absenders, wie ihn der Kunde sieht.
     */
    public function authorName(): string
    {
        return match (true) {
            $this->author instanceof PortalUser, $this->author instanceof User => $this->author->name,
            default => 'System',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }
}
