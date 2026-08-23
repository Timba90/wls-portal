# Projektanweisung für Claude Code

## 1. Projektziel

Baue eine interne Verwaltungskonsole für die Betreuung eigener Kunden.

Es handelt sich ausdrücklich **nicht um ein Kundenportal**.

Die Anwendung wird ausschließlich intern von wenigen Mitarbeitern verwendet.

Das langfristige Ziel ist eine zentrale Übersicht über:

- Kunden
- Ansprechpartner
- Webseiten und andere Projekte
- Domains
- Hosting
- Nextcloud und andere Dienste
- individuelle Kundenleistungen
- vereinbarte Preise
- Einkaufskosten
- Margen
- Abrechnungsintervalle
- bisherige Abrechnungen
- später Lexoffice
- später Nuxbe
- später do.de / ResellerInterface
- später E-Mail-Integration
- später KI-Funktionen

Der wichtigste wirtschaftliche Zweck der Anwendung ist langfristig:

> Jederzeit erkennen zu können, welche Leistungen bei welchem Kunden bestehen, welchen Preis wir vereinbart haben und welche Leistungen möglicherweise noch nicht oder nicht mehr korrekt abgerechnet werden.

---

# 2. WICHTIG: Aktueller Entwicklungsumfang

Nicht versuchen, sofort das gesamte spätere System zu implementieren.

Wir bauen zunächst einen stabilen Kern.

## Phase 1

Implementiert werden:

1. Benutzer und Authentifizierung
2. Kunden
3. Ansprechpartner
4. Artikel-/Leistungskatalog
5. Artikelvarianten
6. Kundenleistungen
7. Preise und Preisverlauf
8. Kategorien
9. Tags
10. benutzerdefinierte Felder
11. Notizen
12. Dokumente
13. Audit-Historie
14. zentrale Suche
15. grundlegendes Dashboard
16. Archivierung

Noch **nicht** implementieren:

- Lexoffice API
- Nuxbe API
- Rechnungserzeugung
- do.de API
- Domains
- Webseiten/Projekte
- Hosting-Systeme
- E-Mail-Synchronisierung
- KI
- automatische Abrechnungserkennung
- komplexe Backup-Oberfläche

Diese zukünftigen Bereiche müssen bei Architekturentscheidungen berücksichtigt werden, dürfen Phase 1 aber nicht unnötig kompliziert machen.

---

# 3. Technischer Stack

Verwende:

- Laravel 13
- aktuellste vollständig von Laravel 13 unterstützte stabile PHP-Version
- Livewire 4
- TallStackUI v3
- Tailwind CSS 4
- Alpine.js
- Laravel Fortify
- MySQL oder MariaDB
- Redis
- Laravel Horizon
- Vite
- Pest
- S3-kompatiblen Object Storage für Uploads
- Nginx + PHP-FPM für Produktion

Sessions:

- Redis

Cache:

- Redis

Queues:

- Redis

Queue Monitoring:

- Laravel Horizon

Währung:

- ausschließlich EUR

Sprache:

- ausschließlich Deutsch

---

# 4. Laravel-Agent-Unterstützung

Bevor du mit größerer Implementierung beginnst:

1. Lies und beachte die aktuelle Laravel-Anleitung für Coding Agents:

`https://laravel.com/for/agents`

2. Installiere Laravel Boost:

`composer require laravel/boost --dev`

3. Installiere anschließend die Agent-Unterstützung:

`php artisan boost:install`

4. Stelle sicher, dass Laravel Boost für Claude Code verfügbar ist.

Falls nötig:

`claude mcp add -s local -t stdio laravel-boost php artisan boost:mcp`

Nutze Laravel Boost aktiv, wenn Informationen über Laravel APIs, aktuelle Konventionen oder Framework-Funktionen benötigt werden.

---

# 5. Projektprinzipien

## 5.1 Modularer Monolith

Die Anwendung bleibt eine Laravel-Anwendung.

Keine Microservices.

Strukturiere die Fachlogik aber sauber nach Bereichen.

Beispielsweise:

- Customers
- Contacts
- Catalog
- Services
- Documents
- Notes
- CustomFields
- Tags
- Audit
- Authentication

Keine unnötige Enterprise-Abstraktion.

---

## 5.2 Businesslogik nicht in Livewire-Komponenten verstecken

Livewire-Komponenten sind hauptsächlich für:

- UI-State
- Formulare
- Benutzerinteraktion
- Darstellung

Geschäftslogik gehört in:

- Actions
- Services
- Domain-Klassen
- Policies
- Value Objects
- Enums

Beispiel:

`CreateCustomer`
`ArchiveCustomer`
`CreateCustomerService`
`SchedulePriceChange`
`DuplicateCustomerService`

---

## 5.3 Keine riesigen Models

Models sollen Beziehungen, Casts und kleine fachliche Helfer enthalten.

Komplexe Vorgänge gehören in Actions oder Services.

---

## 5.4 Keine Geldbeträge als Float

Geld immer als Integer in Cent speichern.

Beispiel:

`4999 = 49,99 EUR`

Kein `float` oder `double` für Geld verwenden.

---

## 5.5 Keine Hard Deletes für Geschäftsdaten

Kunden, Leistungen und andere wichtige Geschäftsdaten werden grundsätzlich archiviert.

Keine endgültige Löschung über die normale Oberfläche.

---

# 6. Benutzer

Es gibt wenige interne Benutzer.

Alle Benutzer haben zunächst dieselben Rechte.

Noch kein komplexes Rollen-/Rechtesystem bauen.

Benutzer werden nur manuell durch einen bestehenden Benutzer angelegt.

Keine öffentliche Registrierung.

## Authentifizierung

Laravel Fortify verwenden.

Eigene Livewire-/TallStackUI-Oberfläche bauen.

Funktionen:

- Login
- Logout
- Passwort vergessen
- Passwort zurücksetzen
- 2FA über TOTP
- Recovery Codes
- Sessionverwaltung

2FA ist zunächst optional.

Die Architektur soll später erlauben, 2FA global verpflichtend zu machen.

Passwort:

- mindestens 12 Zeichen
- Großbuchstaben
- Kleinbuchstaben
- Zahl
- Sonderzeichen

Passwörter laufen nicht automatisch ab.

Automatischer Logout nach:

30 Minuten Inaktivität.

---

# 7. Kunden

Es gibt zwei Kundentypen:

- Unternehmen
- Privatperson

Keine Kundengruppen.

Keine Beziehungen zwischen Kunden.

---

# 8. Kundennummer

Jeder Kunde erhält automatisch eine interne Kundennummer.

Format:

`KD-00001`

danach:

`KD-00002`

usw.

Regeln:

- automatisch vergeben
- niemals wiederverwenden
- nach Erstellung unveränderlich
- archivierte Kundennummern niemals erneut vergeben

Die Vergabe muss transaktionssicher sein.

Race Conditions verhindern.

---

# 9. Firmenkunde

Ein Firmenkunde benötigt mindestens:

- ID
- Kundennummer
- Firmenname
- Kurzbezeichnung
- internes Kürzel
- Status
- created_at
- updated_at
- archived_at

Kurzbezeichnung und Kürzel müssen nicht eindeutig sein.

Status zunächst:

- aktiv
- archiviert

Nicht speichern:

- Rechtsform
- USt-ID
- Steuernummer
- Branche
- allgemeine E-Mail-Adresse
- allgemeine Telefonnummer
- Homepage
- Rechnungsbezeichnung
- Anschrift

Diese Daten können später über ERP-Systeme ergänzt werden.

---

# 10. Privatkunde

Privatkunden besitzen:

- Kundennummer
- Anrede
- akademischer Titel optional
- Vorname
- Nachname
- Geburtsdatum optional
- Geschlecht
- Kurzbezeichnung
- internes Kürzel
- Status

Geschlecht:

- männlich
- weiblich
- divers

Privatkunden können mehrere E-Mail-Adressen und Telefonnummern besitzen.

Jeweils genau eine kann primär sein.

---

# 11. Ansprechpartner

Separate Ansprechpartner existieren nur bei Firmenkunden.

Ein Firmenkunde kann beliebig viele Ansprechpartner besitzen.

Ein Ansprechpartner kann mehreren Firmenkunden zugeordnet werden.

Die Rollen können je Firmenzuordnung unterschiedlich sein.

## Ansprechpartner-Stammdaten

- Anrede
- akademischer Titel
- Vorname
- Nachname
- Geschlecht
- Geburtsdatum optional
- bevorzugte Kontaktart
- aktiv / inaktiv je Kundenzuordnung

Kein eigenes Firmenfeld.

Ein Ansprechpartner muss mindestens einem Kunden zugeordnet sein.

---

# 12. Kontaktdaten

Ansprechpartner können mehrere haben:

- E-Mail-Adressen
- Telefonnummern

Typen beispielsweise:

- geschäftlich
- privat
- mobil

Primäre E-Mail-Adresse und Telefonnummer können je Kundenzuordnung unterschiedlich sein.

Bevorzugte Kontaktart:

- E-Mail
- Telefon
- Mobil

---

# 13. Ansprechpartnerrollen

Rollen müssen frei definierbar sein.

Beispiele:

- Geschäftsführung
- Technik
- Buchhaltung
- Einkauf
- Marketing

Ein Ansprechpartner kann mehrere Rollen je Kunde besitzen.

Zusätzlich:

- Hauptansprechpartner ja/nein
- Priorität
- Rechnungskontakt ja/nein

Mehrere Hauptansprechpartner sind erlaubt.

---

# 14. Ansprechpartner-Vertretungen

Vertretungen sollen modellierbar sein.

Je:

- Kunde
- Rolle

können mehrere Vertretungen mit Priorität definiert werden.

Diese Funktion darf bei der ersten UI-Version einfach gehalten werden.

---

# 15. Kundenübersicht

Die zentrale Kundenliste soll mindestens zeigen können:

- Kundennummer
- Firmenname / Personenname
- Kurzbezeichnung
- Kürzel
- Status
- Anzahl Leistungen
- Monatsumsatz
- Jahresumsatz
- Kosten
- Marge

Noch nicht vorhandene Kennzahlen dürfen zunächst 0 sein.

Filter:

- Suche
- aktiv / archiviert
- Tags
- interner Verantwortlicher
- Umsatzbereich
- Margenbereich

Alle sinnvollen Spalten sortierbar.

---

# 16. Kundendetailseite

Die Kundendetailseite ist die wichtigste Ansicht der Anwendung.

Nutze Tabs oder vergleichbare TallStackUI-Navigation.

Vorgesehene Bereiche:

- Übersicht
- Ansprechpartner
- Leistungen
- Notizen
- Dokumente
- Historie

Später ergänzbar um:

- Projekte
- Domains
- Rechnungen
- Angebote
- E-Mails
- ERP
- technische Daten

---

# 17. Artikel-/Leistungskatalog

Es gibt einen zentralen Katalog.

Beispiele:

- Webhosting
- Managed Hosting
- Webseitenwartung
- Domain .de
- Nextcloud
- Backup
- SSL
- Monitoring
- Support
- Webentwicklung

Verwende fachlich intern möglichst `Product` oder `CatalogItem`.

In der deutschen Oberfläche kann die Bezeichnung:

`Artikel / Leistung`

verwendet werden.

---

# 18. Katalogartikel

Ein Katalogartikel besitzt mindestens:

- Name
- interne Bezeichnung
- Beschreibung
- Kategorie
- Unterkategorie optional
- Tags
- Standard-Einkaufspreis
- Standard-Verkaufspreis
- Standard-Abrechnungsintervall
- Status
- strukturierte Leistungsbestandteile
- Custom Fields

Status:

- aktiv
- archiviert

---

# 19. Kategorien

Kategorien frei definierbar.

Unterkategorien unterstützen.

Beispiel:

Hosting
→ Managed Hosting

Webentwicklung
→ Wartung

Cloud
→ Nextcloud

Noch keine beliebig tiefe Baumstruktur bauen.

Eine Hierarchiestufe Kategorie + Unterkategorie reicht zunächst.

---

# 20. Tags

Generisches Tag-System bauen.

Tags sollen mindestens nutzbar sein für:

- Kunden
- Ansprechpartner
- Katalogartikel
- Kundenleistungen

Später problemlos für:

- Projekte
- Domains

erweiterbar.

Verwende polymorphe Beziehungen, wenn dies sauber umsetzbar ist.

---

# 21. Artikelvarianten

Katalogartikel dürfen Varianten besitzen.

Beispiel:

Managed Hosting

Varianten:

- Basic
- Business
- Premium

Jede Variante kann eigene Werte besitzen:

- Name
- Beschreibung
- Einkaufspreis
- Verkaufspreis
- Abrechnungsintervall
- Leistungsbestandteile
- Custom Fields

---

# 22. Leistungsbestandteile

Eine Leistung kann strukturierte Bestandteile besitzen.

Beispiel:

Managed Website:

- Hosting
- tägliches Backup
- Monitoring
- Updates
- 30 Minuten Support

Bestandteile benötigen mindestens:

- Titel
- Beschreibung optional
- Sortierung
- optional Einkaufspreis
- optional Verkaufspreis

Die Struktur muss sowohl für Katalogartikel als auch Kundenleistungen verwendbar sein.

---

# 23. Abrechnungsintervalle

Nicht nur einen einfachen String speichern.

Das Datenmodell soll flexibel sein.

Empfohlen:

- interval_unit
- interval_count

Beispiele:

Einmalig:

`unit = once`

Monatlich:

`unit = month`
`count = 1`

Quartalsweise:

`unit = month`
`count = 3`

Jährlich:

`unit = year`
`count = 1`

Damit bleiben später weitere Intervalle möglich.

---

# 24. Kundenleistung – ZENTRALES MODELL

Die Kundenleistung ist eines der wichtigsten Modelle des gesamten Systems.

Nicht nur eine Pivot-Tabelle bauen.

Es muss ein eigenes Model sein.

Beispiel:

Katalog:

`Managed Hosting Business`
`59 EUR / Monat`

Kunde:

`Müller GmbH`

Kundenleistung:

`Hosting Webseite Müller`
`49 EUR / Monat`

Damit kann der Kunde vom Standard abweichende Vereinbarungen besitzen.

---

# 25. Kundenleistung – Felder

Mindestens:

- Kunde
- optional Katalogartikel
- optional Artikelvariante
- interner Anzeigename
- ERP-/Rechnungsbezeichnung
- Beschreibung
- Status
- Einkaufspreis
- Verkaufspreis
- Abrechnungsintervall
- Leistungsbeginn
- separates Abrechnungsstartdatum
- geplantes erstes Abrechnungsdatum
- Tags
- Kategorie
- Unterkategorie
- Custom Fields
- interne Verantwortliche
- created_at
- updated_at
- archived_at

Eine Kundenleistung darf auch **ohne Katalogartikel** erstellt werden.

Damit sind vollständig individuelle Leistungen möglich.

---

# 26. Kundenindividuelle Abweichungen

Eine Kundenleistung kann vom Katalogstandard abweichen.

Beispielsweise:

- anderer Preis
- andere Kosten
- anderer Leistungsumfang
- anderes Abrechnungsintervall
- andere Beschreibung
- andere Bestandteile

Es muss später nachvollziehbar sein:

Standard:

`59 EUR`

Kunde:

`49 EUR`

Die Herkunft aus dem Katalog darf nicht verloren gehen.

---

# 27. Status Kundenleistung

Feste Statuswerte zunächst:

- geplant
- aktiv
- pausiert
- beendet
- archiviert

Archivierte Leistungen:

- sind read-only
- können nicht mehr verändert werden
- bleiben historisch erhalten

---

# 28. Bewusst nicht abrechnen

Eine Kundenleistung kann markiert werden als:

`Bewusst nicht abrechnen`

Mögliche feste Gründe:

- inklusive
- Kulanz
- Eigenleistung
- kostenlos

Diese Kennzeichnung gilt, bis sie manuell entfernt wird.

Nach Entfernen beginnt die normale Betrachtung erst wieder ab diesem Zeitpunkt.

Keine rückwirkende automatische Nachberechnung.

---

# 29. Preise

Jede relevante Leistung kann besitzen:

- Einkaufspreis
- Verkaufspreis

Daraus berechnen:

- absolute Marge
- Marge in Prozent
- Deckungsbeitrag

Monats- und Jahreswerte automatisch normalisieren.

Beispiel:

120 EUR jährlich

entspricht:

10 EUR monatlich

---

# 30. Preisverlauf

Preise dürfen nicht einfach überschrieben werden.

Preisänderungen historisieren.

Eine Preisänderung besitzt:

- alter Preis
- neuer Preis
- Wirksamkeitsdatum
- Benutzer
- Zeitpunkt

Zukünftige Preisänderungen erlauben.

Keine rückwirkenden Preisänderungen.

Mehrere zukünftige Preisänderungen dürfen geplant werden.

Zum Wirksamkeitsdatum automatisch aktivieren.

---

# 31. Artikelvorlagen und Kundenleistungen

Wenn eine Kundenleistung auf einem Katalogartikel basiert, bleibt diese Verbindung erhalten.

Wird der Katalogartikel später geändert:

Bestehende Kundenleistungen niemals automatisch verändern.

Stattdessen muss später möglich sein:

- Unterschiede anzeigen
- alle Änderungen übernehmen
- einzelne Änderungen übernehmen

Diese Funktion muss nicht vollständig in der ersten Iteration gebaut werden.

Das Datenmodell darf sie aber nicht verhindern.

---

# 32. Custom Fields

Baue ein generisches Custom-Field-System.

Unterstützte Typen:

- Text
- Textarea
- Zahl
- Datum
- Ja/Nein
- Auswahl
- Mehrfachauswahl
- URL
- E-Mail

Custom Fields sollen später nutzbar sein für:

- Kunden
- Projekte
- Domains
- Leistungen

Phase 1 mindestens:

- Kunden
- Katalogartikel
- Kundenleistungen

Definitionen enthalten:

- Name
- Schlüssel
- Typ
- Pflichtfeld
- Standardwert
- Optionen
- Sortierung
- aktiv/inaktiv

Bedingte Sichtbarkeit architektonisch ermöglichen.

Keinen unnötig komplizierten Rule Builder in der ersten Version bauen.

---

# 33. Notizen

Generisches Notizsystem.

Notizen mindestens bei:

- Kunden
- Ansprechpartnern
- Kundenleistungen

Felder:

- Text
- Kategorie
- Benutzer
- Datum/Uhrzeit

Mögliche Kategorien:

- Allgemein
- Technik
- Abrechnung
- Vertrag

Kategorien später erweiterbar gestalten.

---

# 34. Dokumente

Dokumente mindestens bei:

- Kunden
- Ansprechpartnern
- Kundenleistungen

Speicherung:

privater S3-kompatibler Object Storage.

Keine öffentlichen direkten URLs.

Zugriff nur über die Anwendung.

Maximale Dateigröße:

100 MB

Grundsätzlich alle Dateitypen erlauben.

Gefährliche ausführbare Dateitypen über konfigurierbare Blockliste sperren.

Keine Malware-Prüfung in Phase 1.

---

# 35. Dateiversionierung

Dokumente besitzen Versionen.

Neue Datei ersetzt alte Version nicht physisch.

Speichern:

- Dokument
- Version
- S3-Key
- Dateiname
- MIME-Type
- Dateigröße
- Benutzer
- Uploadzeitpunkt

Die neueste Version ist automatisch die aktuelle Version.

Keine manuelle „gültige Version“-Kennzeichnung.

---

# 36. Dokumentvorschau

Soweit technisch sinnvoll:

- Bilder direkt anzeigen
- PDF anzeigen
- Office-Dateien nur, wenn eine saubere und sichere Vorschau möglich ist

Keine unnötige Eigenentwicklung eines Office-Renderers.

---

# 37. Audit Log

Das System benötigt eine vollständige Änderungshistorie.

Mindestens protokollieren:

- Benutzer
- Datum/Uhrzeit
- Aktion
- Model
- Datensatz-ID
- alter Wert
- neuer Wert

Beispiele:

`Verkaufspreis: 49,00 → 59,00`

`Status: aktiv → pausiert`

`Ansprechpartner hinzugefügt`

`Leistung archiviert`

Audit-Daten nicht über die normale Anwendung löschbar machen.

---

# 38. Archivierung

Kunden:

dürfen archiviert werden, wenn keine aktiven Leistungen mehr vorhanden sind.

Kundenleistungen:

können archiviert werden.

Archivierte Kundenleistungen sind vollständig schreibgeschützt.

Archivierte Datensätze tauchen nicht in der normalen globalen Suche auf.

Es soll später eine separate Archivsuche geben.

---

# 39. Globale Suche

Globale Suche oben im Layout.

Phase 1 durchsuchen:

- Kunden
- Kundennummer
- Ansprechpartner
- E-Mail-Adressen
- Katalogartikel
- Kundenleistungen

Suchergebnis:

- Typ
- Name
- Zusatzinformation

Aktion:

`Öffnen`

Keine komplexen Schnellaktionen.

Archivierte Datensätze ausschließen.

---

# 40. Navigation

Desktop:

linke Sidebar.

Oben:

globale Suche.

Keine:

- Favoriten
- zuletzt verwendeten Datensätze
- Schnellzugriffe

Mobile:

Burger-Menü.

---

# 41. Design

Verwende konsequent TallStackUI-Komponenten, sofern geeignete Komponenten existieren.

Keine parallele eigene Komponentenbibliothek entwickeln.

Das Design soll:

- professionell
- ruhig
- übersichtlich
- datenorientiert
- modern

sein.

Keine verspielte SaaS-Optik.

Keine unnötigen Animationen.

---

# 42. Dark Mode

Unterstützen:

- Systemeinstellung automatisch erkennen
- Benutzer darf manuell überschreiben

---

# 43. Responsive Design

Die Anwendung muss vollständig funktionieren auf:

- Desktop
- Tablet
- Smartphone

Mobile Ansichten dürfen kompakter dargestellt werden.

Keine Desktop-Tabelle einfach unbenutzbar horizontal über den Bildschirm quetschen.

---

# 44. Tabellen

Zentrale Tabellen sollen unterstützen:

- Suche
- Filter
- Sortierung
- Pagination
- Spalten ein-/ausblenden
- Spaltenreihenfolge
- Spaltenbreite

Tabellenkonfiguration gilt global und nicht benutzerspezifisch.

Keine gespeicherten Filteransichten.

---

# 45. Dashboard Phase 1

Noch kein überladenes Controlling-Dashboard.

Phase 1 zeigt zunächst:

- Anzahl aktive Kunden
- Anzahl archivierte Kunden
- Anzahl Katalogartikel
- Anzahl aktive Kundenleistungen
- monatlicher Soll-Umsatz
- jährlicher Soll-Umsatz
- monatliche Kosten
- jährliche Kosten
- Marge

Später kommen:

- Lexoffice
- tatsächliche Abrechnung
- Prognosen
- Charts
- offene Abrechnung
- Domains
- Projekte

---

# 46. Technische Datenbankstruktur

Entwirf das Schema sorgfältig vor Erstellung der Migrationen.

Erwartete Kernmodelle ungefähr:

- User
- Customer
- CustomerContact
- Contact
- ContactEmail
- ContactPhone
- ContactRole
- ContactAssignment
- Product
- ProductVariant
- ServiceComponent
- CustomerService
- CustomerServiceComponent
- Category
- Tag
- CustomFieldDefinition
- CustomFieldValue
- Note
- Document
- DocumentVersion
- Price
- AuditLog

Die konkrete Tabellenstruktur darf verbessert werden.

Nicht blind diese Liste übernehmen, wenn eine sauberere relationale Struktur sinnvoller ist.

Vor den Migrationen:

1. ER-Modell überlegen
2. Beziehungen prüfen
3. unique constraints definieren
4. Foreign Keys definieren
5. Indizes definieren
6. Archivierungsstrategie definieren

---

# 47. Datenbankregeln

Verwende:

- Foreign Keys
- sinnvolle Indizes
- Unique Constraints
- DB-Transaktionen für kritische Prozesse

Verlasse dich bei wichtigen Datenintegritätsregeln nicht ausschließlich auf UI-Validierung.

---

# 48. Enums

Für feste fachliche Zustände PHP Enums verwenden.

Beispielsweise:

- CustomerType
- CustomerStatus
- CustomerServiceStatus
- Gender
- ContactMethod
- BillingIntervalUnit

Keine magischen Strings durch die gesamte Anwendung verteilen.

---

# 49. Formulare

Formulare benötigen:

- serverseitige Validierung
- verständliche deutsche Fehlermeldungen
- Livewire Validation
- keine unnötigen JavaScript-Sonderlösungen

TallStackUI verwenden für:

- Inputs
- Selects
- Modals
- Tables
- Alerts
- Tabs
- Dropdowns
- Datepicker
- Dialoge
- Notifications

---

# 50. Sicherheit

Alle Seiten außer Login/Passwort-Reset authentifizierungspflichtig.

CSRF-Schutz nicht umgehen.

Keine Secrets ins Git Repository.

API-Secrets werden später verschlüsselt in der Datenbank gespeichert.

Noch keine Integration-Secrets in Phase 1 benötigt.

---

# 51. Audit und Sicherheit von Änderungen

Besonders relevante Aktionen brauchen eine Bestätigung:

- Kunde archivieren
- Leistung archivieren
- zukünftige Preisänderung löschen
- Dokumentversion löschen, falls diese Funktion später existiert

Kein Browser-`confirm()`.

TallStackUI Dialog verwenden.

---

# 52. Tests

Pest verwenden.

Nicht nur Happy Path testen.

Mindestens Tests für:

## Kunden

- Firmenkunde anlegen
- Privatkunde anlegen
- Kundennummer wird korrekt vergeben
- Kundennummer ist unveränderlich
- Kundennummer wird nicht wiederverwendet
- Kunde mit aktiver Leistung kann nicht archiviert werden

## Ansprechpartner

- Ansprechpartner anlegen
- mehrere E-Mails
- mehrere Telefonnummern
- Rollen zuweisen
- Hauptansprechpartner
- Ansprechpartner mehreren Kunden zuweisen

## Katalog

- Artikel anlegen
- Variante anlegen
- Preis speichern
- Kategorie
- Tags

## Kundenleistungen

- Katalogartikel Kunden zuweisen
- individuellen Preis setzen
- komplett individuelle Leistung anlegen
- Marge berechnen
- Abrechnungsintervall
- bewusst nicht abrechnen
- archivierte Leistung nicht mehr editierbar

## Preise

- zukünftige Preisänderung
- keine rückwirkende Preisänderung
- korrekter aktueller Preis
- korrekte Monats-/Jahresnormalisierung

---

# 53. Factories und Seeder

Für alle zentralen Models Factories erstellen.

Development Seeder erstellen mit realistischen Testdaten.

Beispielsweise:

- 20 Firmenkunden
- 5 Privatkunden
- 40 Ansprechpartner
- 20 Katalogartikel
- mehrere Varianten
- 50 Kundenleistungen

Keine Fantasiedaten wie:

`Test Test`
`Foo Bar`
`Example Item`

Realistische deutsche Beispieldaten verwenden.

---

# 54. Codequalität

Vor jedem größeren Meilenstein ausführen:

`composer test`

bzw. die im Projekt konfigurierte Test-Suite.

Zusätzlich:

- Laravel Pint
- statische Probleme prüfen
- npm build

Keine Änderungen als fertig deklarieren, solange Tests fehlschlagen.

---

# 55. Git-Workflow

Git von Anfang an verwenden.

Sinnvolle kleine Commits.

Beispielsweise:

`feat: add customer domain model`

`feat: add contact management`

`feat: add catalog products and variants`

`feat: add customer services`

`feat: add price history`

`test: cover customer service pricing`

Keine riesigen Commits mit 100 ungeordneten Änderungen.

---

# 56. Dokumentation

Pflege im Repository:

`docs/PROJECT.md`

mit der aktuellen fachlichen Architektur.

Zusätzlich:

`docs/BACKLOG.md`

für bewusst verschobene Funktionen.

Wenn eine neue Architekturentscheidung getroffen wird, kurz dokumentieren.

---

# 57. Zukunft: Lexoffice

Noch nicht implementieren.

Die Architektur muss später einen Adapter ermöglichen.

Lexoffice wird ausschließlich lesend verwendet.

Geplant:

- Lexoffice-Kunden
- Angebote
- Rechnungen
- Rechnungspositionen
- Zahlungsstatus

Synchronisation:

einmal täglich.

Portal-Kunden können später mit Lexoffice-Kunden verknüpft werden.

Eindeutige Treffer dürfen automatisch verknüpft werden.

Mehrdeutige Treffer müssen manuell ausgewählt werden.

Lexoffice wird langfristig abgeschaltet.

---

# 58. Zukunft: Abrechnungskontrolle

Das spätere Hauptziel ist:

Bei jeder Kundenleistung erkennen:

- noch nie abgerechnet
- aktuell abgerechnet
- wieder fällig
- Lexoffice-Zuordnung unklar
- bewusst nicht abrechnen

Die letzte Abrechnung soll später automatisch aus Lexoffice-Rechnungen ermittelt werden.

Historische Lexoffice-Rechnungspositionen sollen über:

- Kunde
- Text
- Betrag
- Zeitraum
- gelernte Zuordnungsregeln

Kundenleistungen zugeordnet werden.

Noch nicht in Phase 1 implementieren.

---

# 59. Zukunft: Nuxbe

Nuxbe ist das zukünftige ERP.

Später soll Nuxbe bidirektional angebunden werden.

Über das Portal sollen später Rechnungen in Nuxbe erzeugt werden.

Vor Rechnungserstellung:

- Vorschau
- anschließend Entwurf oder fertige Rechnung erzeugen

Noch nicht implementieren.

---

# 60. Zukunft: Domains

Später Domainverwaltung hinzufügen.

Hauptprovider:

do.de / ResellerInterface.

Geplant:

- vollständige API-Integration
- Registrierung
- Transfers
- DNS
- Kontakte
- Verlängerung
- Kündigung
- täglicher Sync

Kritische Aktionen benötigen Bestätigung.

Domains anderer Provider müssen ebenfalls manuell verwaltbar sein.

Noch nicht implementieren.

---

# 61. Zukunft: Projekte

Später Projekte hinzufügen.

Mögliche Typen:

- Webseite
- Shop
- Web-App
- API
- internes Tool

Projekttypen sollen frei definierbar sein.

Custom Fields pro Projekttyp.

Technische Felder später:

- Live URL
- Staging URL
- Framework
- Version
- Repository

Noch nicht implementieren.

---

# 62. Zukunft: E-Mail

Später providerunabhängige E-Mail-Integration.

Mögliche Provider:

- Microsoft 365
- Exchange
- IMAP

E-Mails automatisch anhand von:

- Absender
- Empfänger
- Ansprechpartner
- Domain

Kunden zuordnen.

Noch nicht implementieren.

---

# 63. Zukunft: KI

Allgemeine austauschbare KI-Schicht vorsehen.

Mögliche Provider:

- OpenAI
- lokale LLMs
- andere APIs

Externe KI darf niemals Secrets erhalten.

Lokale/on-prem KI darf bei später entsprechender Konfiguration auch auf Secrets zugreifen.

Noch keine KI in Phase 1 implementieren.

---

# 64. Keine voreilige Abstraktion

Wichtig:

Nicht für jede zukünftige Funktion jetzt bereits 20 Interfaces und leere Klassen erstellen.

Die Architektur soll erweiterbar sein, aber Phase 1 soll produktiv nutzbaren Code liefern.

Faustregel:

> Heute benötigte Abstraktion bauen. Morgen benötigte Erweiterbarkeit berücksichtigen. Übermorgen benötigte Klassen noch nicht schreiben.

---

# 65. Vorgehensweise für Claude Code

Arbeite in klaren Meilensteinen.

## Meilenstein 1 – Projektbasis

- Laravel 13
- PHP
- Livewire 4
- TallStackUI
- Tailwind
- MySQL
- Redis
- Horizon
- Fortify
- Pest
- Laravel Boost
- Basislayout
- Auth

Danach Tests.

---

## Meilenstein 2 – Kunden

- Datenmodell
- Kundennummer
- Firmenkunde
- Privatkunde
- Kundenliste
- Kundenformular
- Detailseite
- Archivierung

Danach Tests.

---

## Meilenstein 3 – Ansprechpartner

- Kontakte
- E-Mails
- Telefonnummern
- Rollen
- Kundenzuordnung
- Hauptansprechpartner

Danach Tests.

---

## Meilenstein 4 – Katalog

- Kategorien
- Produkte
- Varianten
- Leistungsbestandteile
- Tags

Danach Tests.

---

## Meilenstein 5 – Kundenleistungen

- Kundenleistung
- Verbindung zum Katalog
- individuelle Leistungen
- Preise
- Kosten
- Abrechnungsintervalle
- Leistungsbestandteile
- Status
- bewusst nicht abrechnen

Danach Tests.

---

## Meilenstein 6 – Preislogik

- Preisverlauf
- zukünftige Preise
- Wirksamkeitsdatum
- Marge
- Monatswert
- Jahreswert

Danach Tests.

---

## Meilenstein 7 – Zusatzfunktionen

- Notizen
- Dokumente
- S3
- Dateiversionierung
- Custom Fields
- Audit Log

Danach Tests.

---

## Meilenstein 8 – Übersicht

- Dashboard
- globale Suche
- zentrale Leistungsübersicht
- Archivansichten
- Responsive Optimierung

Danach komplette Test-Suite.

---

# 66. Verhalten bei Unklarheiten

Wenn während der Entwicklung eine fachliche Frage entsteht:

Nicht einfach eine weitreichende Businessentscheidung erfinden.

Bei kleinen technischen Entscheidungen:

selbstständig die Laravel-konforme und einfachste sinnvolle Lösung wählen.

Bei Entscheidungen, die Auswirkungen auf:

- Preise
- Abrechnung
- Kundenstruktur
- Datenverlust
- ERP
- Vertragslogik

haben:

Frage den Auftraggeber.

---

# 67. Bestehende Entscheidungen nicht eigenmächtig ändern

Die Anforderungen in dieser Datei sind die aktuelle fachliche Wahrheit.

Wenn eine Implementierungsentscheidung mit einer Anforderung kollidiert:

nicht stillschweigend die Anforderung ändern.

Stattdessen:

1. Problem erklären
2. technische Alternative nennen
3. Entscheidung einholen

---

# 68. Definition of Done

Ein Meilenstein gilt erst als abgeschlossen, wenn:

- Migrationen funktionieren
- Seeder funktionieren
- Tests erfolgreich sind
- UI funktioniert
- Dark Mode funktioniert
- Mobile Darstellung brauchbar ist
- Validierung vorhanden ist
- Audit-relevante Änderungen protokolliert werden
- keine offensichtlichen N+1 Queries bestehen
- keine Debug-Ausgaben vorhanden sind
- keine Secrets committed wurden
- `npm run build` erfolgreich ist
- Tests erfolgreich sind

---

# 69. Erste konkrete Aufgabe

Beginne jetzt ausschließlich mit:

## Projektbasis + Datenmodellplanung

Noch nicht sofort alle Features programmieren.

Zuerst:

1. aktuelles Repository analysieren
2. Laravel-Agent-Anweisungen laden
3. Laravel Boost einrichten
4. Stack prüfen
5. geplantes Datenmodell für Phase 1 entwerfen
6. Models und Beziehungen auflisten
7. Tabellen und wichtige Constraints beschreiben
8. potenzielle Probleme im Datenmodell identifizieren
9. anschließend mit Meilenstein 1 beginnen
10. danach Meilenstein für Meilenstein weiterarbeiten

Erstelle keine Domains-, Lexoffice-, Nuxbe-, Projekt-, E-Mail- oder KI-Funktionen in Phase 1.

Das erste produktive Ziel ist:

> Ich kann meine Kunden vollständig erfassen, einen sauberen Leistungskatalog pflegen und jedem Kunden seine tatsächlichen individuellen Leistungen inklusive Kosten, Verkaufspreis und Abrechnungsintervall zuordnen.

Wenn dieses Ziel stabil funktioniert, wird das System schrittweise erweitert.