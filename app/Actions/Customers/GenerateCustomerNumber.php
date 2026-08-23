<?php

namespace App\Actions\Customers;

use App\Support\Numbering\SequenceGenerator;

/**
 * Erzeugt die naechste interne Kundennummer im Format KD-00001.
 */
class GenerateCustomerNumber
{
    public const SEQUENCE_KEY = 'customer_number';

    public const PREFIX = 'KD-';

    public function __construct(private readonly SequenceGenerator $sequences) {}

    public function __invoke(): string
    {
        return $this->sequences->format(
            self::PREFIX,
            $this->sequences->next(self::SEQUENCE_KEY),
        );
    }
}
