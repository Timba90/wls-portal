# Backlog

Bewusst auf einen späteren Zeitpunkt verschobene Funktionen. Die Architektur
berücksichtigt sie, Phase 1 implementiert sie nicht.

## Aus der Anforderung ausdrücklich als Zukunft markiert

| Thema                          | Anmerkung zur Vorbereitung                                                                 |
|--------------------------------|--------------------------------------------------------------------------------------------|
| Lexoffice (nur lesend)         | Adapter später; Kundenleistungen tragen bereits `billing_label` für die Zuordnung.           |
| Automatische Abrechnungskontrolle | `do_not_bill_since` / `do_not_bill_released_at` halten fest, ab wann normal betrachtet wird. |
| Nuxbe (ERP, bidirektional)     | Rechnungsbezeichnung und Preise liegen strukturiert vor.                                     |
| Rechnungserzeugung             | —                                                                                            |
| do.de / ResellerInterface      | —                                                                                            |
| Domains                        | Tags und Custom Fields sind polymorph und ohne Migration erweiterbar.                        |
| Webseiten                      | dito                                                                                         |
| Hosting-Systeme                | —                                                                                            |
| E-Mail-Synchronisierung        | `email_addresses` ist polymorph und indiziert.                                               |
| KI-Funktionen                  | —                                                                                            |
| Komplexe Backup-Oberfläche     | —                                                                                            |

## Innerhalb von Phase 1 bewusst vereinfacht

| Thema                                     | Entscheidung                                                                                                                        |
|-------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| Katalogänderungen übernehmen              | Umgesetzt: Reiter „Katalog" im Leistungsdetail, Hinweis mit Filter in der Leistungsübersicht, Markierung im Artikeldetail, zwei MCP-Werkzeuge. Siehe AE-18. |
| Bedingte Sichtbarkeit von Custom Fields   | Die Spalte `visibility_condition` existiert, es gibt bewusst keinen Rule Builder.                                                    |
| Mehrere interne Verantwortliche           | Kunde und Kundenleistung tragen je einen `responsible_user_id`. Ein Wechsel auf n:m ist eine reine Pivot-Migration.                  |
| Office-Dokumentvorschau                   | Bilder und PDF werden angezeigt. Office-Formate werden zum Download angeboten — kein eigener Renderer.                               |
| Malware-Prüfung von Uploads               | Nicht in Phase 1. Es greift eine konfigurierbare Blockliste gefährlicher Dateiendungen.                                              |
| Archivsuche                               | Archivierte Datensätze sind aus der globalen Suche ausgeschlossen. Die separate Archivsuche kommt über die Archivansichten.          |
| Gespeicherte Filteransichten              | Ausdrücklich nicht gewünscht.                                                                                                       |
| Rollen- und Rechtesystem                  | Alle Benutzer haben dieselben Rechte. Policies existieren als Einstiegspunkt.                                                        |
| Beliebig tiefe Kategoriebäume             | Eine Hierarchiestufe (Kategorie + Unterkategorie).                                                                                   |

## Aus dem Entwurf bewusst nicht übernommen

Der Design-Canvas `WLS Portal.dc.html` zeigt Bereiche, die es in Phase 1 nicht
gibt. Sie wurden nicht nachgebaut, weil die Oberfläche sonst Funktionen
vortäuschen würde, die keine Daten haben.

| Element im Entwurf | Grund |
|---|---|
| Navigationspunkt „Rechnungen", Panel „Rechnungslauf & offene Posten", Reiter „Rechnungen" am Kunden | Rechnungserzeugung ist ausdrücklich Zukunft. |
| Navigationspunkt „Zeiterfassung" | Nicht Teil des Funktionsumfangs. |
| Geschäftsjahr-Umschalter in der Seitenleiste | Es gibt keine Geschäftsjahre im Datenmodell. |
| „Mit Passkey anmelden" auf dem Login | Passkeys wurden bewusst deaktiviert; vorgesehen sind Passwort und TOTP. |
| Balken- und Anteilsdiagramme auf der Übersicht | Charts sind ausdrücklich als spätere Erweiterung vermerkt. |
| Reiter „Rechnungen" im Kundendetail | Rechnungserzeugung ist ausdrücklich Zukunft; die Spalten Nummer, Betrag und Fälligkeit hätten keine Quelle. |
| Panel „Fristen" im Kundendetail samt Schaltflächen „Verlängern" und „Notiz anlegen" | Das Datenmodell kennt weder ein Laufzeitende noch eine Kündigungsfrist. Vertragslogik ist laut Anforderung rückfragepflichtig und wird nicht erfunden. |
| Spalte „Laufzeit bis" in der Leistungstabelle des Kundendetails | Gleicher Grund; an ihrer Stelle steht „Beginn" aus `service_start_date`. |
| Schaltfläche „Duplizieren" im Artikeldetail | Das Projekt kennt kein Duplizieren von Katalogartikeln. Eine Schaltfläche ohne Funktion wäre schlechter als keine; die Funktion selbst wirft Fragen auf (welcher interne Name, welche Varianten und Tags werden mitkopiert), die zum Katalog gehören und nicht nebenbei entschieden werden sollten. |
| Spalte „Laufzeit bis" in der Verwendungstabelle des Artikeldetails | Wie im Kundendetail: kein Laufzeitende im Modell. An ihrer Stelle steht „Beginn". |
| Panel „Abrechnung" im Leistungsdetail samt Rechnungstabelle und „Jetzt abrechnen" | Rechnungserzeugung ist ausdrücklich Zukunft. An seiner Stelle steht der Preisverlauf — die Vertragsposition führt ihn ohnehin vollständig. |
| Panel „Frist" im Leistungsdetail samt „Verlängern" und „Leistung kündigen" | Kein Laufzeitende und keine Kündigungsfrist im Modell. An seiner Stelle steht ein Aktionen-Panel mit den Vorgängen, die es wirklich gibt: Preis anpassen, Status wechseln, archivieren. |
| Spalte „MwSt" im Artikelkatalog samt Netto/Brutto-Umschalter | Es gibt im ganzen Projekt kein Steuerfeld. Steuerdaten kommen laut Datenmodell später aus dem ERP; ein fest verdrahteter Satz von 19 % wäre geraten. |

## Abweichungen in der Umsetzung

| Element im Entwurf | Umsetzung |
|---|---|
| „Letzte Aktivität" in der Kundenliste | Zeigt `updated_at` des Kundendatensatzes als relative Angabe. Der Entwurf lässt offen, worauf sich die Aktivität bezieht; mit der Abrechnungskontrolle lässt sich das später schärfen. |
| Feste sechs Spalten der Kundenliste | Umgesetzt als Voreinstellung. Die global konfigurierbaren Spalten aus Meilenstein 2 bleiben erhalten: sieben weitere Spalten sind zuschaltbar, das Raster rechnet die Anteile dann neu. |
| Ansprechpartner-Spalte der Kundenliste | Zeigt bei Firmenkunden den als Hauptansprechpartner markierten Kontakt, bei Privatkunden die Person selbst. |
| „Preisentwicklung" im Artikeldetail | Ein Katalogartikel führt keinen eigenen Preisverlauf — den gibt es nur je Kundenleistung. Die Einträge kommen deshalb aus der Änderungshistorie, die Änderungen an Einkaufs- und Verkaufspreis ohnehin unveränderlich festhält. Der erste Eintrag ist die Anlage des Artikels. |
| „Varianten" im Artikeldetail | Im Entwurf nicht vorgesehen, aber die einzige Stelle, an der Varianten verwaltet werden. Als dritte Karte unter den beiden Panels des Entwurfs eingefügt. |
| Linke Spalte des Leistungsdetails | Der Entwurf zeigt zwei gestapelte Panels. Umgesetzt als Reiterleiste wie im Kundendetail, weil dort zusätzlich Bestandteile, Notizen, Dokumente und eigene Felder unterkommen müssen — gestapelt wäre die Seite mehrere Bildschirme lang geworden. |
| „Abweichung" im Leistungsdetail | Vergleicht den Verkaufspreis der Leistung mit dem heutigen Listenpreis des Basisartikels. Positiv heißt teurer als der Katalog. |
| Spalte „Einheit" im Artikelkatalog | Das Modell kennt keine Mengeneinheit, sondern ein Abrechnungsintervall. Die Spalte heißt deshalb „Turnus" und zeigt monatlich, jährlich, einmalig. |
| Feste sieben Spalten des Artikelkatalogs | Wie bei der Kundenliste als Voreinstellung umgesetzt; Einkaufspreis, Marge, Varianten und Tags bleiben zuschaltbar. |
| Zähler der Kategorienleiste | Zeigen, was der Klick tatsächlich einlöst — also Artikel mit dieser Kategorie **oder** Unterkategorie, unter Berücksichtigung von Suche, Status und Tag. Kein Aufsummieren der Unterkategorien: ein Artikel dort trägt immer auch die Oberkategorie und würde doppelt zählen. |

### Projekte

| Element im Entwurf | Umsetzung |
|---|---|
| „Budget & Stunden" im Projektdetail | Nicht übernommen. Es gibt weder Zeiterfassung noch Rechnungsstellung; das Panel hätte nur erfundene Zahlen zeigen können. Der Geldteil steht als Projektvolumen aus den Positionen im Kopf und in der Positionstabelle. |
| Fortschrittsbalken ohne Meilensteine | Der Entwurf zeigt immer einen Balken. Ohne Meilensteine gibt es nichts zu messen — Liste und Detail schreiben dort „Keine Meilensteine". |
| Linke Spalte des Projektdetails | Der Entwurf stapelt „Plan & Meilensteine" und „Positionen". Umgesetzt als Reiterleiste wie im Kunden- und Leistungsdetail, weil zusätzlich Notizen, Dokumente, eigene Felder und Verlauf unterkommen müssen. |
| Spalte „Nächste Termine" | Zeigt bewusst alle offenen Meilensteine laufender Projekte, unabhängig vom Statusfilter der Tabelle. Sie ist ein Terminkalender, kein zweites Abbild der gefilterten Liste. |
| Feste sechs Spalten der Projektliste | Wie bei Kundenliste und Artikelkatalog als Voreinstellung umgesetzt; Projektnummer, Typ, Verantwortlich, Beginn, laufendes Volumen und Meilensteinzahl bleiben zuschaltbar. |

## Offene fachliche Rückfragen

| Frage                                                                                                     | Aktuelle Annahme                                                                                              |
|-----------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------|
| Sollen einmalige Leistungen (`once`) in Monats- und Jahresumsatz einfließen?                               | Nein — sie sind nicht wiederkehrend und würden die Kennzahlen verfälschen.                                     |
| Was bedeutet ein fehlender Einkaufspreis?                                                                  | `0` Cent, also keine Kosten. Beide Preisspalten sind `NOT NULL DEFAULT 0`.                                     |
| Zählt eine pausierte Leistung zum Soll-Umsatz?                                                             | Nein — nur `aktiv` fließt in Umsatz-, Kosten- und Margenkennzahlen ein.                                        |
| Zählen Leistungen mit „Bewusst nicht abrechnen" zum Soll-Umsatz?                                           | Nein. Sie werden separat ausgewiesen.                                                                          |
| Fließen Projektpositionen in Umsatz, Kosten und Marge des Kunden ein?                                      | Nein. Abgerechnet wird über Kundenleistungen; ein Projekt ist Planung. Das Projektvolumen steht separat.        |
| Darf eine Projektposition vom Listenpreis abweichen?                                                       | Ja. Katalogartikel und Kundenleistung liefern Name und Preis nur als Vorschlag; beides bleibt frei änderbar.    |
