<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Wird geworfen, wenn ein Datensatz die Voraussetzungen fuer die Archivierung
 * nicht erfuellt.
 */
class ArchivingNotPossibleException extends RuntimeException
{
    //
}
