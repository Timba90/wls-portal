<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Exceptions\ImmutableAttributeException;
use App\Models\Concerns\Auditable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anfrage eines Kunden — aus dem Portal, intern erfasst oder per Schnittstelle.
 *
 * Die Ticketnummer wird wie Kunden- und Projektnummer ueber die Sequenztabelle
 * vergeben und ist danach unveraenderlich.
 */
#[Fillable([
    'ticket_number',
    'customer_id',
    'project_id',
    'portal_user_id',
    'assigned_user_id',
    'subject',
    'status',
    'priority',
    'source',
    'external_reference',
    'last_activity_at',
    'closed_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $ticket): void {
            if ($ticket->isDirty('ticket_number')) {
                throw new ImmutableAttributeException(
                    'Die Ticketnummer kann nach der Erstellung nicht mehr geändert werden.'
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Der Kundenzugang, der das Ticket eroeffnet hat.
     *
     * @return BelongsTo<PortalUser, $this>
     */
    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Nachrichten, die der Kunde sehen darf — ohne interne Notizen.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::openStates());
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeForCustomer(Builder $query, Customer $customer): void
    {
        $query->whereBelongsTo($customer);
    }

    /**
     * @return array<string, string>
     */
    public function auditLabels(): array
    {
        return [
            'ticket_number' => 'Ticketnummer',
            'customer_id' => 'Kunde',
            'project_id' => 'Projekt',
            'portal_user_id' => 'Kundenzugang',
            'assigned_user_id' => 'Zuständig',
            'subject' => 'Betreff',
            'status' => 'Status',
            'priority' => 'Priorität',
            'source' => 'Herkunft',
            'external_reference' => 'Externe Referenz',
            'closed_at' => 'Geschlossen am',
        ];
    }

    /**
     * Die Aktivitaetszeit aendert sich mit jeder Nachricht und gehoert nicht
     * in die Historie.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return ['created_at', 'updated_at', 'last_activity_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'source' => TicketSource::class,
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
