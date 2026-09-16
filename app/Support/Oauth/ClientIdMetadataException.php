<?php

namespace App\Support\Oauth;

use RuntimeException;

/**
 * Ein Client-ID-Metadatendokument ist nicht verwendbar.
 *
 * Die Meldung nennt den Grund so genau, dass der Betreiber des Clients ihn
 * beheben kann — sie landet im Protokoll, nicht beim Benutzer.
 */
class ClientIdMetadataException extends RuntimeException {}
