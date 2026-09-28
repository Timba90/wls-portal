<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Catalog\KatalogSuchen;
use App\Mcp\Tools\Catalog\ProduktLesen;
use App\Mcp\Tools\Catalog\ProduktLoeschen;
use App\Mcp\Tools\Catalog\ProduktSpeichern;
use App\Mcp\Tools\Contacts\AnsprechpartnerArchivieren;
use App\Mcp\Tools\Contacts\AnsprechpartnerLesen;
use App\Mcp\Tools\Contacts\AnsprechpartnerLoeschen;
use App\Mcp\Tools\Contacts\AnsprechpartnerSpeichern;
use App\Mcp\Tools\Contacts\AnsprechpartnerSuchen;
use App\Mcp\Tools\Customers\KundeArchivieren;
use App\Mcp\Tools\Customers\KundeLesen;
use App\Mcp\Tools\Customers\KundeLoeschen;
use App\Mcp\Tools\Customers\KundenSuchen;
use App\Mcp\Tools\Customers\KundeSpeichern;
use App\Mcp\Tools\Insights\GlobalSuchen;
use App\Mcp\Tools\Insights\HistorieLesen;
use App\Mcp\Tools\Insights\KennzahlenLesen;
use App\Mcp\Tools\Insights\NotizSpeichern;
use App\Mcp\Tools\Pricing\PreisaenderungAbbrechen;
use App\Mcp\Tools\Pricing\PreisaenderungPlanen;
use App\Mcp\Tools\Pricing\PreisDirektSetzen;
use App\Mcp\Tools\Pricing\PreisverlaufLesen;
use App\Mcp\Tools\Projects\MeilensteinSpeichern;
use App\Mcp\Tools\Projects\PositionSpeichern;
use App\Mcp\Tools\Projects\ProjektArchivieren;
use App\Mcp\Tools\Projects\ProjekteSuchen;
use App\Mcp\Tools\Projects\ProjektLesen;
use App\Mcp\Tools\Projects\ProjektLoeschen;
use App\Mcp\Tools\Projects\ProjektSpeichern;
use App\Mcp\Tools\Projects\ProjektTeamSetzen;
use App\Mcp\Tools\Projects\ProjekttypenVerwalten;
use App\Mcp\Tools\Registrar\BestandSuchen;
use App\Mcp\Tools\Registrar\BestandZuordnen;
use App\Mcp\Tools\Registrar\DnsAenderungAnwenden;
use App\Mcp\Tools\Registrar\DnsAenderungPlanen;
use App\Mcp\Tools\Registrar\DnsZoneLesen;
use App\Mcp\Tools\Registrar\DomainSpeichern;
use App\Mcp\Tools\Services\KatalogabgleichLesen;
use App\Mcp\Tools\Services\KatalogaenderungEntscheiden;
use App\Mcp\Tools\Services\LeistungenSuchen;
use App\Mcp\Tools\Services\LeistungLesen;
use App\Mcp\Tools\Services\LeistungLoeschen;
use App\Mcp\Tools\Services\LeistungSpeichern;
use App\Mcp\Tools\Services\LeistungStatusSetzen;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('WLS Portal')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Zugang zur internen Kunden- und Leistungsverwaltung.

    Grundsaetzliches:

    - Alle Betraege sind ganzzahlige Cent. Der Wert `cents` ist massgeblich,
      `formatiert` dient nur der Anzeige. Niemals mit Nachkommastellen rechnen.
    - Waehrung ist ausschliesslich Euro.
    - Datumsangaben werden als JJJJ-MM-TT erwartet und geliefert.
    - Der uebliche Weg, einen Datensatz aus dem Betrieb zu nehmen, ist das
      Archivieren. Die Werkzeuge mit `-loeschen` entfernen Daten endgueltig
      und sind nicht umkehrbar; sie verlangen deshalb eine Bestaetigung.
    - Jede Aenderung wird mit dem Benutzer des verwendeten Tokens in der
      Aenderungshistorie festgehalten.

    Zu Projekten:

    - Ein Projekt haengt an genau einem Kunden, nicht an einer Kundenleistung.
      Der Bezug zu einzelnen Leistungen entsteht ueber die Positionen.
    - Das Projektvolumen ist die Summe der *einmaligen* Positionen.
      Wiederkehrende stehen getrennt daneben, weil sie einen Monatsbetrag
      meinen.
    - Der Fortschritt kommt aus den Meilensteinen und ist `null`, solange es
      keine gibt.

    Zum Bestand an Domains und Zertifikaten:

    - Der technische Stand kommt aus der Schnittstelle des Registrars. Wem ein
      Eintrag gehoert, weiss nur das Portal — diese Zuordnung wird von Hand
      gesetzt und vom Import nicht ueberschrieben.
    - `zuordnung: ohne_kunde` nennt den offenen Rest nach einem Import.
      `zuordnung: ohne_leistung` ist etwas anderes: der Kunde steht fest, nur
      die Verbindung zur Abrechnung fehlt noch.
    - Die Kundenleistung ist freiwillig. Nicht jede Domain wird einzeln
      berechnet, manche laeuft in einem Paket mit.
    - `domain-speichern` ist fuer Domains, deren Registrar hier keine
      Schnittstelle hat. Den technischen Stand einer *importierten* Domain
      aendert es nicht — der kommt vom Anbieter. Bei wem eine von Hand
      gepflegte Domain liegt, gehoert in eine Notiz (`notiz-speichern` mit
      `typ: domain`): das Anbieterfeld benennt Anschluesse, keine Registrare
      ohne solchen.

    Zu DNS-Eintraegen:

    - `dns-zone-lesen` zeigt die Zone beim Anbieter. Geaendert wird in zwei
      Schritten: `dns-aenderung-planen` zeigt Ist, Soll und eine Pruefsumme,
      `dns-aenderung-anwenden` fuehrt sie mit genau dieser Pruefsumme aus.
      Ohne vorherigen Plan gibt es keine Aenderung.
    - **Rechne einen Eintragswert nie stillschweigend selbst zusammen.** Ein
      Pruefbericht nennt oft einen „Soll-Record", der nur eine Ergaenzung
      meint: wer bei SPF das Soll wortgleich schreibt, entfernt die uebrigen
      Absender und stellt den Mailversand ab. Den vollstaendigen neuen Wert
      bestimmt der Mensch — zeige Ist und Soll, benenne den Unterschied, und
      frage nach, bevor du anwendest.
    - An einer Stelle koennen mehrere Eintraege desselben Typs liegen (SPF
      neben einem Bestaetigungs-Token, mehrere A oder MX). Dann sagt
      `alt_inhalt`, welcher gemeint ist; ohne die Angabe bricht die Planung ab
      und nennt die vorhandenen.
    - Ein zweiter SPF- oder DMARC-Eintrag macht beide ungueltig. Ergaenzen
      heisst deshalb `aendern`, nicht `anlegen`.
    - Passt die Pruefsumme nicht mehr, hat sich die Zone bewegt: neu planen und
      den neuen Stand ansehen, nicht die alte Pruefsumme erneut schicken.

    Zum Einstieg eignen sich `kennzahlen-lesen` fuer den Gesamtueberblick und
    `global-suchen`, wenn nur ein Stichwort bekannt ist.
    MARKDOWN)]
class PortalServer extends Server
{
    /**
     * Der Werkzeugsatz ist groesser als die Standardseite von 15. Clients, die
     * den Cursor nicht auswerten, saehen sonst nur einen Teil davon.
     */
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        KundenSuchen::class,
        KundeLesen::class,
        KundeSpeichern::class,
        KundeArchivieren::class,
        KundeLoeschen::class,

        AnsprechpartnerSuchen::class,
        AnsprechpartnerLesen::class,
        AnsprechpartnerSpeichern::class,
        AnsprechpartnerArchivieren::class,
        AnsprechpartnerLoeschen::class,

        KatalogSuchen::class,
        ProduktLesen::class,
        ProduktSpeichern::class,
        ProduktLoeschen::class,

        LeistungenSuchen::class,
        LeistungLesen::class,
        LeistungSpeichern::class,
        LeistungStatusSetzen::class,
        LeistungLoeschen::class,
        KatalogabgleichLesen::class,
        KatalogaenderungEntscheiden::class,

        ProjekteSuchen::class,
        ProjektLesen::class,
        ProjektSpeichern::class,
        ProjektArchivieren::class,
        ProjektLoeschen::class,
        MeilensteinSpeichern::class,
        PositionSpeichern::class,
        ProjektTeamSetzen::class,
        ProjekttypenVerwalten::class,

        PreisverlaufLesen::class,
        PreisaenderungPlanen::class,
        PreisaenderungAbbrechen::class,
        PreisDirektSetzen::class,

        BestandSuchen::class,
        BestandZuordnen::class,
        DomainSpeichern::class,

        DnsZoneLesen::class,
        DnsAenderungPlanen::class,
        DnsAenderungAnwenden::class,

        KennzahlenLesen::class,
        GlobalSuchen::class,
        NotizSpeichern::class,
        HistorieLesen::class,
    ];
}
