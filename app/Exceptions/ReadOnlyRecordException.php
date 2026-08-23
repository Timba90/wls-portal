<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Wird geworfen, wenn ein schreibgeschuetzter Datensatz veraendert werden soll.
 */
class ReadOnlyRecordException extends RuntimeException
{
    //
}
