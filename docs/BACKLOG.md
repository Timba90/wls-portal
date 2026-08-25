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
| Browser-Tests | Aktiv. `tests/Browser/` läuft als eigene Suite in `phpunit.xml` und fährt einen echten Chromium; der ganze Lauf dauert rund zehn Sekunden. Der frühere Hänger lag nicht an der Suite: der Playwright-Server erbt `stdout`, und wenn dort eine Pipe hängt (`php artisan test \| tail`), bleibt sie nach dem Ende von PHP offen und die Shell wartet. In eine Datei oder auf die Konsole geschrieben läuft alles durch — in der CI also unproblematisch. |
| Was die Browser-Tests abdecken | Den Rundgang über alle Seiten (Konsolenfehler, fehlende Assets), die Bestätigungsdialoge (archivieren, abbrechen, löschen mit Parameter, jeweils mit Prüfung am Datensatz) und die Formulare in Modals: Nicht abrechnen, Preisänderung, Meilenstein, Position, Variante, Notiz sowie die vier Katalog- und Systemlisten. Gegengeprüft: stellt man die alte, tote TallStackUI-Schnittstelle wieder her, schlagen sie fehl. Genau diese Lücke hatten 557 serverseitige Tests offengelassen. |
| Fehlerseiten | 404, 403, 419, 500 und 503 in der Gestaltung der Anwendung statt Laravels Standardseiten, jeweils mit Weg zurück. Besonders 419: die Sitzung endet nach 30 Minuten (AE-9), wer ein Formular aus einem alten Tab abschickt landet dort — der Text sagt, was zu tun ist. |
| Tabellensemantik der Rasterlisten | Die Listen sind Raster aus `<div>`, damit die Spaltenanteile des Entwurfs sauber sitzen. Ohne Rollen war die Struktur für Screenreader nicht vorhanden. Rahmen, Kopfzeile und Zellen tragen sie jetzt ausdrücklich; der Zeilenlink liegt in der ersten Zelle und spannt sich per `after` über die Zeile, weil ein `<a>` mit `role="row"` seine Linkrolle verlöre. |
| SVG-Vorschau | SVG zählt als Bild, kann aber Skripte tragen. Die Vorschau setzt zwar `default-src 'none'`, was das blockiert — SVG wird trotzdem zum Download angeboten statt eingebettet, damit die Ungefährlichkeit nicht allein an einem Kopfzeilenfeld hängt. |
| Statusleisten der Listen | Zählten je Schaltfläche einzeln — bei den Projekten acht Abfragen für eine Leiste. Ersetzt durch eine gruppierte Zählung (`App\Support\StatusTally`). Ein neuer Status kostet damit keine weitere Abfrage mehr. |
| Katalogabgleich in der Leistungsübersicht | Der Vergleich lässt sich nicht in SQL führen und lädt alle nicht archivierten Leistungen mit Katalogherkunft. Das ist bei der erwarteten Größenordnung unkritisch, wächst aber linear mit dem Bestand. Vorerst je Aufbau zwischengespeichert, damit Hinweis und Filter ihn sich teilen. |
| Leistungsübersicht und Ansprechpartnerliste | Beide liefen noch auf der Roh-Tabelle von TallStackUI, während Kundenliste, Artikelkatalog und Projektliste längst das Raster des Entwurfs nutzen. Auf dieselbe Bauart umgestellt: Kennzahlkacheln, Rastertabelle mit Spaltenanteilen, Statuspillen, Initialenkachel, ganze Zeile als Link. |
| Voreingestellte Spalten der Kundenliste | Kunde, Umsatz/Mon., Umsatz/Jahr, Kosten/Mon., Marge/Mon., Status. Ansprechpartner, Leistungen und „Letzte Aktivität" hat der Auftraggeber aus der Voreinstellung genommen; sie bleiben zuschaltbar. Die vier Geldspalten sind der geplante Umsatz — abgerechnet wird im Portal nicht, alle Umsatzzahlen sind Soll-Werte aus den aktiven, wiederkehrenden Leistungen. |
| Voreingestellte Spalten der Leistungsübersicht | Sieben statt dreizehn: Kunde, Leistung, Turnus, Verkauf, Monatswert, Status, Abrechnung. Katalogartikel, Kategorie, Einkauf, Marge, Verantwortlich und Leistungsbeginn bleiben zuschaltbar. Die Abrechnungsspalte bleibt voreingestellt, weil sie erklärt, warum eine Leistung *nicht* in den Kennzahlen steht. |
| Zähler der Kategorienleiste | Zeigen, was der Klick tatsächlich einlöst — also Artikel mit dieser Kategorie **oder** Unterkategorie, unter Berücksichtigung von Suche, Status und Tag. Kein Aufsummieren der Unterkategorien: ein Artikel dort trägt immer auch die Oberkategorie und würde doppelt zählen. |

### Preise

| Element | Umsetzung |
|---|---|
| Umrechnung beim Wechsel des Abrechnungsintervalls | Die Preisfelder gelten je Abrechnungsperiode. Ein Wechsel von jährlich auf monatlich ließ den Betrag bisher unangetastet — aus 15,00 € im Jahr wurden stillschweigend 15,00 € im Monat. Ein- und Verkaufspreis werden jetzt umgerechnet, in beiden Formularen (Kundenleistung und Katalogartikel) und einschließlich der Bestandteile. |
| Automatisch statt Vorschlag | Auf Ansage des Auftraggebers. Wer nach dem Wechsel einen anderen Preis will, überschreibt ihn von Hand. |
| Rundung | Kaufmännisch auf ganze Cent, ebenfalls auf Ansage. 14,99 € im Jahr ergeben 1,25 € im Monat und damit 15,00 € im Jahr — der Jahreswert kann um wenige Cent abweichen, weil sich nicht jeder Betrag zwölfteln lässt. Beträge liegen als ganze Cent vor (§ Geldbeträge), ein exakter Zwölftelwert ist nicht speicherbar. |
| Einmalige Beträge | Bleiben unverändert. Ein einmaliger Preis bezieht sich auf keinen Zeitraum, den man umrechnen könnte; aus 500 € einmalig werden beim Wechsel auf monatlich nicht 41,67 €. |
| Vorgaben aus dem Katalog | Werden nicht umgerechnet. Der Artikel liefert Preis und Intervall gemeinsam und passend zueinander — eine Umrechnung machte den Vorschlag sofort falsch. Ein Test hält das fest. |

| Wie in Browser-Tests geprüft wird | Auf Text aus dem Inhalt, nie auf den Rahmen. Der Rahmen eines Dialogs oder Modals steht auch im geschlossenen Zustand in der Seite und ist null Pixel hoch — eine Sichtbarkeitsprüfung darauf meldet Fehlschläge, die keine sind. Das ist mir beim Suchen viermal passiert. |

### Oberfläche

| Element | Umsetzung |
|---|---|
| Bestätigungsdialoge und Hinweise | Riefen durchgehend `$dialog.confirm({...})` und `$tallstackui.toast()` auf — beides die Schnittstelle der Vorgängerversion. TallStackUI 3 bietet `$tsui.interaction('dialog')` beziehungsweise `$tsui.interaction('toast')`. Sämtliche 56 Aufrufe waren dadurch wirkungslos: Archivieren, Löschen, Reaktivieren, jede Erfolgsmeldung. Umgestellt; ein Test in `tests/Feature/Extras/FrontendApiTest.php` hält die Namen fest. |
| Warum das kein Test fand | Die Aufrufe stehen in Blade-Attributen und laufen erst im Browser. Die Seite lädt, der Knopf ist da, ein Feature-Test sieht keinen Unterschied. Zwei Wächter greifen jetzt: die Browser-Suite klickt die Dialoge wirklich, und ein Textwächter über die Ansichten fängt die alten Namen schon vor dem Browserstart ab. |
| `wireable()` bei Dialogen | TallStackUI weist einen Dialog mit Livewire-Methode ohne Angabe der Komponente still ab. Der zweite Test stellt sicher, dass jeder Dialog mit Methode auch `wireable($wire.id)` nennt. |

### Übersicht

| Element | Umsetzung |
|---|---|
| Panels „Bestand" und „Nicht in den Kennzahlen" | Auf Ansage entfernt. Die Bestandszahlen stehen unverändert in den Kennzahlkacheln und in den Zählern der Navigation. Warum eine Leistung nicht in den Umsatz zählt, sagt weiterhin die Abrechnungsspalte der Leistungsübersicht — sie ist genau deshalb voreingestellt. |
| Grafik „Abrechnung je Monat" | Zeigt bewusst nur die kommenden zwölf Monate. Rückwärts wäre die Reihe falsch: seither archivierte Leistungen fehlen im heutigen Bestand, die vergangenen Monate sähen dadurch zu niedrig aus. Für einen echten Rückblick bräuchte es eine Historie der Leistungen, die es nicht gibt (§61). |
| Kennzahlkachel gegen Grafik | Die Kachel „Umsatz / Monat" normalisiert: eine Jahresleistung steht dort mit einem Zwölftel. Die Grafik zeigt stattdessen den Rhythmus — dieselbe Leistung fällt in genau einem Monat an. Beide Zahlen sind richtig und beantworten verschiedene Fragen; die Summe der zwölf Monate deckt sich mit dem Jahreswert der Kachel. |
| Leistungen ohne Abrechnungsdatum | Ein Rhythmus jenseits des Monats lässt sich ohne Anfangsdatum nicht auf einen Monat legen. Solche Leistungen werden unter der Grafik als Fehlbetrag ausgewiesen statt in einen geratenen Monat gelegt, und der Hinweis verlinkt sie: „Auflisten" führt in die Leistungsübersicht, gefiltert auf genau diese Menge (`?abrechnung=no_schedule`). Ein Monatsrhythmus braucht kein Datum — er trifft ohnehin jeden Monat. |
| Zahl und Liste dürfen nicht auseinanderlaufen | Der Filter benutzt denselben Scope `withoutBillingSchedule()`, den die Grafik zählt; ein Test hält fest, dass beide dieselbe Menge meinen. Ohne das wäre die Zahl unter der Grafik irgendwann etwas anderes als die Liste dahinter. |
| Sechs Kennzahlkacheln | Zwei Reihen zu dritt, nicht sechs nebeneinander: bei sechs Spalten bricht ein sechsstelliger Betrag wie „12.384,24 €" um. |
| Diagramme ohne Bibliothek | Beide Grafiken sind Balken aus `<div>`. Eine Diagrammbibliothek wäre eine neue Abhängigkeit für zwei Darstellungen, die sich mit den vorhandenen Tokens zeichnen lassen. Für Screenreader liegt unter dem Säulendiagramm dieselbe Reihe als Tabelle. |

### Projekte

| Element im Entwurf | Umsetzung |
|---|---|
| „Budget & Stunden" im Projektdetail | Nicht übernommen. Es gibt weder Zeiterfassung noch Rechnungsstellung; das Panel hätte nur erfundene Zahlen zeigen können. Der Geldteil steht als Projektvolumen aus den Positionen im Kopf und in der Positionstabelle. |
| Fortschrittsbalken ohne Meilensteine | Der Entwurf zeigt immer einen Balken. Ohne Meilensteine gibt es nichts zu messen — Liste und Detail schreiben dort „Keine Meilensteine". |
| Linke Spalte des Projektdetails | Der Entwurf stapelt „Plan & Meilensteine" und „Positionen". Umgesetzt als Reiterleiste wie im Kunden- und Leistungsdetail, weil zusätzlich Notizen, Dokumente, eigene Felder und Verlauf unterkommen müssen. |
| Spalte „Nächste Termine" | Entfernt. Der Auftraggeber hat sie aus der Übersicht genommen; Meilensteine stehen im Projektdetail unter „Plan & Meilensteine". |
| Deadline und Fortschritt in der Übersicht | Ebenfalls auf Ansage entfernt, aber nicht gelöscht: beide Spalten bleiben zuschaltbar, damit niemand die Information verliert, der sie braucht. |
| Kunde in der Übersicht | Auf Ansage aus der Voreinstellung genommen. Die Spalte bleibt zuschaltbar, und die Suche findet Projekte weiterhin über den Kundennamen. |
| Feste sieben Spalten der Projektliste | Projekt, Umsatz, Umsatz/Mon., Backup, Security, Updates, Status. Kunde, Projektnummer, Typ, Verantwortlich, Beginn, Deadline, Fortschritt, „Betrieb geprüft" und Meilensteinzahl bleiben zuschaltbar. |
| Betriebsampeln Backup, Security, Updates | Es gibt keine Überwachung, die diese Werte liefern könnte — Hosting-Systeme und Backup-Oberfläche sind §61-Zukunft. Die drei Ampeln werden von Hand gepflegt und tragen deshalb das Datum der letzten Prüfung. Voreinstellung ist „Ungeprüft", ausdrücklich **kein** Grün: ein nie geprüftes Projekt ist nicht in Ordnung, sondern ungeprüft — und zählt in der Kachel „Betrieb prüfen" mit. |
| Kennzahlen der Projektübersicht | „Überfällig" und „Termine 14 Tage" sind mit Deadlines und Terminen entfallen. An ihrer Stelle stehen Umsatz, Umsatz/Mon. und die Zahl der offenen Projekte mit einer Ampel abseits von Grün. |
| Projekttypen | Auf Ansage fest auf Laravel, Shopify und WordPress gesetzt und mit den Markenzeichen in ihren Markenfarben hinterlegt (Pfade aus simple-icons, CC0). Die Tabelle bleibt erweiterbar; ein Typ ohne bekanntes Zeichen bekommt seine Initialenkachel. |

## Offene fachliche Rückfragen

| Frage                                                                                                     | Aktuelle Annahme                                                                                              |
|-----------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------|
| Sollen einmalige Leistungen (`once`) in Monats- und Jahresumsatz einfließen?                               | Nein — sie sind nicht wiederkehrend und würden die Kennzahlen verfälschen.                                     |
| Was bedeutet ein fehlender Einkaufspreis?                                                                  | `0` Cent, also keine Kosten. Beide Preisspalten sind `NOT NULL DEFAULT 0`.                                     |
| Zählt eine pausierte Leistung zum Soll-Umsatz?                                                             | Nein — nur `aktiv` fließt in Umsatz-, Kosten- und Margenkennzahlen ein.                                        |
| Zählen Leistungen mit „Bewusst nicht abrechnen" zum Soll-Umsatz?                                           | Nein. Sie werden separat ausgewiesen.                                                                          |
| Fließen Projektpositionen in Umsatz, Kosten und Marge des Kunden ein?                                      | Nein. Abgerechnet wird über Kundenleistungen; ein Projekt ist Planung. Das Projektvolumen steht separat.        |
| Darf eine Projektposition vom Listenpreis abweichen?                                                       | Ja. Katalogartikel und Kundenleistung liefern Name und Preis nur als Vorschlag; beides bleibt frei änderbar.    |
