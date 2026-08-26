<?php

namespace App\Support\Registrar;

use App\Enums\RegistrarProvider;

/**
 * Was ein Registrar koennen muss, damit das Portal seinen Bestand einlesen kann.
 *
 * Nur lesend. Registrieren, verlaengern und kuendigen bleiben bewusst im
 * Portal des Anbieters: das Portal hier ist eine Verwaltungsoberflaeche, kein
 * Registrar-Frontend, und ein versehentlicher Schreibzugriff kostet Geld.
 */
interface RegistrarClient
{
    public function provider(): RegistrarProvider;

    /**
     * Ist der Anschluss vollstaendig eingerichtet?
     *
     * Ohne Zugangsdaten soll der Import mit einer klaren Meldung abbrechen und
     * nicht mit einer Ausnahme aus der Tiefe.
     */
    public function isConfigured(): bool;

    /**
     * @return iterable<int, RemoteDomain>
     */
    public function domains(): iterable;

    /**
     * @return iterable<int, RemoteCertificate>
     */
    public function certificates(): iterable;
}
