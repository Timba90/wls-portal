<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Wird geworfen, wenn ein unveraenderliches Attribut geaendert werden soll.
 */
class ImmutableAttributeException extends RuntimeException
{
    //
}
