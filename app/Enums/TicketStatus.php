<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Bearbeitungsstand eines Tickets.
 *
 * „Wartet auf Kunde" ist ein eigener Zustand und kein Unterfall von „offen":
 * das Team sieht so auf einen Blick, wo es selbst am Zug ist.
 */
enum TicketStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForCustomer = 'waiting_for_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::InProgress => 'In Bearbeitung',
            self::WaitingForCustomer => 'Wartet auf Rückmeldung',
            self::Resolved => 'Gelöst',
            self::Closed => 'Geschlossen',
        };
    }

    public function pillKind(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warn',
            self::WaitingForCustomer => 'warn',
            self::Resolved => 'ok',
            self::Closed => 'mute',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openStates(), true);
    }

    /**
     * @return list<self>
     */
    public static function openStates(): array
    {
        return [self::Open, self::InProgress, self::WaitingForCustomer];
    }
}
