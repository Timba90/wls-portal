<?php

namespace App\Actions\Tickets;

use App\Support\Numbering\SequenceGenerator;

/**
 * Erzeugt die naechste Ticketnummer im Format TK-00001.
 */
class GenerateTicketNumber
{
    public const SEQUENCE_KEY = 'ticket_number';

    public const PREFIX = 'TK-';

    public function __construct(private readonly SequenceGenerator $sequences) {}

    public function __invoke(): string
    {
        return $this->sequences->format(
            self::PREFIX,
            $this->sequences->next(self::SEQUENCE_KEY),
        );
    }
}
