<?php

namespace App\Support\Registrar;

/**
 * Was ein Anschluss koennen muss, um einen DNS-Eintrag zu aendern.
 *
 * Bewusst eine eigene Schnittstelle neben `RegistrarClient` und nicht in ihm:
 * `RegistrarClient` verspricht in seinem Kopf, nur zu lesen, und dieses
 * Versprechen traegt das halbe Projekt. Wer schreiben will, muss deshalb
 * ausdruecklich nach `ZoneWriter` fragen — ein Aufrufer, der nur den Bestand
 * einliest, kann gar nicht versehentlich hier landen.
 *
 * Eine Aenderung ist immer *ein* Vorgang aus hoechstens zwei Teilen: einen
 * bestehenden Eintrag entfernen und einen neuen anlegen. Daraus ergeben sich
 * alle drei Faelle, ohne dass die Schnittstelle sie einzeln kennen muss:
 *
 * - anlegen: nur `$anlegen`
 * - aendern: `$entfernen` und `$anlegen`
 * - loeschen: nur `$entfernen`
 *
 * Beide Anbieter koennen das in einem Schritt, und keiner muss dafuer die Zone
 * neu schreiben: autoDNS ueber `PATCH /zone/{name}/{virtualNameServer}` mit
 * `resourceRecordsRem` und `resourceRecordsAdd`, ResellerInterface ueber
 * `dns/updateRecord` beziehungsweise `dns/createRecord` und
 * `dns/deleteRecord` mit der Record-ID.
 */
interface ZoneWriter
{
    /**
     * Kann dieser Anschluss Eintraege schreiben — und darf er es hier?
     *
     * Beantwortet beides zusammen: die Faehigkeit des Anbieters und den
     * Schalter der Anwendung. Die Oberflaeche und die Werkzeuge fragen das
     * vorher, damit „hier wird nicht geschrieben" ein Zustand bleibt und kein
     * Fehlschlag mitten im Vorgang.
     */
    public function canWriteZone(): bool;

    /**
     * Wendet genau eine Aenderung auf die Zone an.
     *
     * Die Zone kommt frisch gelesen herein: der Anschluss braucht daraus mehr
     * als den Namen (autoDNS den verwaltenden Nameserver), und der Aufrufer hat
     * sie fuer die Pruefsumme ohnehin schon geholt.
     *
     * @param  DnsRecord|null  $entfernen  Der Eintrag, der weichen soll — mit der Kennung des
     *                                     Anbieters, wenn er eine vergibt.
     * @param  DnsRecord|null  $anlegen  Der Eintrag, der danach stehen soll.
     *
     * @throws RegistrarException
     */
    public function applyZoneChange(DnsZone $zone, ?DnsRecord $entfernen, ?DnsRecord $anlegen): void;
}
