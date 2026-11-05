<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Woher ein Ticket stammt.
 *
 * `Api` steht für automatisch erzeugte Tickets — etwa aus einer Überwachung,
 * einem Formular auf der Kundenseite oder einem anderen System.
 */
enum TicketSource: string
{
    use HasOptions;

    case Portal = 'portal';
    case Internal = 'internal';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Portal => 'Kundenportal',
            self::Internal => 'Intern',
            self::Api => 'Schnittstelle',
        };
    }
}
