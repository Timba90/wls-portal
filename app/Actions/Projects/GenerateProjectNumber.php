<?php

namespace App\Actions\Projects;

use App\Support\Numbering\SequenceGenerator;

/**
 * Erzeugt die naechste Projektnummer im Format PR-00001.
 *
 * Wie bei der Kundennummer ueber die Sequenztabelle, damit archivierte oder
 * geloeschte Nummern niemals erneut vergeben werden.
 */
class GenerateProjectNumber
{
    public const SEQUENCE_KEY = 'project_number';

    public const PREFIX = 'PR-';

    public function __construct(private readonly SequenceGenerator $sequences) {}

    public function __invoke(): string
    {
        return $this->sequences->format(
            self::PREFIX,
            $this->sequences->next(self::SEQUENCE_KEY),
        );
    }
}
