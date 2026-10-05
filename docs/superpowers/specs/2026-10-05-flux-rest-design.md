# Flux-REST-Anbindung und Sachkonten

## Ziel und bestätigter Umfang

Das interne WLS Portal erhält einen Flux-Zugang unter „Schnittstellen“, einen
lesenden Verbindungstest und eine Verwaltung für den gezielten Abgleich und
die Anlage von Sachkonten. Gewünschter Kontenrahmen ist SKR04. Die Kontonummern
und Flux-IDs bleiben im Portal für spätere Rechnungszuordnungen erhalten.

Diese erste Umsetzung erstellt keine Rechnungen, bucht keine Belege und
versendet keine E-Mails. Individuelle Abrechnungsintervalle, Voraus- und
Nachberechnung sowie Pauschalen mit internen Bestandteilen bleiben Anforderungen
der folgenden Abrechnungsintegration.

## Bestehende Anwendung

Laravel 13, PHP 8.4, klassenbasiertes Livewire 4. `IntegrationSettings` verwaltet
bislang Registrare. `IntegrationCredential` speichert Zugangsdaten als
`encrypted:array`, ohne sie in die Änderungshistorie oder an die Oberfläche
zurückzugeben. Flux darf nicht als Registrar behandelt werden.

## Entscheidung

Ein eigener Flux-Client unter `app/Support/Flux` und fachliche Actions unter
`app/Actions/Flux` ergänzen die bestehende Anwendung. Die Schnittstellenseite
bekommt eine eigene Flux-Karte. Eine separate Sachkontenseite nutzt diesen Client.

Alternativen: Ein reiner Umgebungsvariablen-Zugang ist schneller, erfüllt aber
nicht die gewünschte Pflege im Portal. Ein generischer API-Proxy würde beliebige
Schreibzugriffe erlauben und ist für diesen Umfang unnötig. Deshalb werden nur
benannte Funktionen für Verbindung und Sachkonten implementiert.

## Zugang und Verbindungstest

- Basisadresse standardmäßig `https://flux.weblab-studio.de/api`.
- Hinterlegt werden Basisadresse, API-Bearer-Token und eine ausdrücklich gewählte
  Flux-Mandanten-ID in `integration_credentials` unter dem Schlüssel `flux`.
- Token wird maskiert eingegeben, verschlüsselt gespeichert und nie vorbefüllt,
  in Meldungen oder Logs ausgegeben. Eine leere Token-Eingabe erhält den alten
  Token. Zugang entfernen löscht nur Zugangsdaten, keine Konten.
- Nur HTTPS, keine Zugangsdaten im URL, keine Query-/Fragmentbestandteile.
  Redirects werden nicht verfolgt; der Token geht ausschließlich an die
  konfigurierte Flux-Basisadresse. Produktionsziel ist der bekannte Flux-Host.
- Der Verbindungstest prüft lesend `/auth/token/validate` und den Zugriff auf
  `/ledger-accounts`. Er erstellt keine Datensätze.
- Feste Verbindungs- und Antwortzeitlimits. Keine automatischen Wiederholungen
  von POST-Anfragen, um doppelte Anlagen nach Zeitüberschreitungen zu vermeiden.

## Sachkonten und gespeicherte Zuordnung

Eine neue lokale Tabelle `flux_ledger_accounts` enthält:

- lokale ID, Verbindungsschlüssel `flux`, Flux-Mandanten-ID;
- Kontonummer als String, Bezeichnung, Kontentyp, Kennzeichen Automatikkonto;
- externe Flux-ID (nullable vor der Anlage), Kontenrahmen `SKR04`;
- Abgleichdatum sowie Ersteller/Änderer und Zeitstempel.

Eindeutigkeit gilt je Verbindung, Mandant und Kontonummer. Der lokale Datensatz
ist ein Verzeichnis und eine technische Zuordnung, keine Buchung. Vorhandene
Flux-Konten werden nicht still umbenannt, gelöscht oder steuerlich umkonfiguriert.
Bei gleicher Nummer und abweichendem Typ wird ein Konflikt angezeigt.

## Oberfläche und Ablauf

1. Zugang speichern und Verbindung prüfen.
2. „Konten abgleichen“ liest sämtliche Seiten von `/ledger-accounts`, filtert
   ausdrücklich auf den konfigurierten Mandanten und speichert die Zuordnungen.
3. Die Sachkontenseite zeigt Nummer, Name, Typ, Automatikkonto und Flux-ID.
4. Ein Formular erlaubt die gezielte Anlage eines fehlenden Kontos mit
   Kontonummer, Bezeichnung und dem von Flux unterstützten Kontentyp.
5. Vor der Anlage wird der aktuelle entfernte Bestand geprüft. Ein vorhandenes
   passendes Konto wird verknüpft, kein zweites angelegt.
6. Ein lokaler Lock serialisiert die Anlage je Mandant und Kontonummer.
7. Nach bestätigtem POST wird die zurückgegebene Flux-ID gespeichert. Bei
   unklarer Antwort oder Timeout wird zunächst erneut gelesen; der Benutzer
   erhält einen Hinweis auf den ungeklärten Status statt einer blinden Wiederholung.

Die Oberfläche übernimmt die vorhandenen deutschen Komponenten und
Authentifizierungsregeln. Fachlogik liegt in Actions, nicht in Livewire.

## Flux-Vertrag

Der geprüfte öffentliche Flux-Quellcode registriert:

- `GET /api/ledger-accounts` für paginierte Listen;
- `GET /api/ledger-accounts/{id}` für ein Konto;
- `POST /api/ledger-accounts` für die Anlage.

Die Anlage erwartet `tenant_id`, `number`, `name`,
`ledger_account_type_enum`; `description`, `uuid` und `is_automatic` sind optional.
Die Implementierung übernimmt die zulässigen Typwerte aus Flux, nicht aus den
vereinfachten MCP-Bezeichnungen. Antwortformate und Pagination werden anhand
von Flux `ResponseHelper` und `BaseController` geprüft und mit HTTP-Fakes getestet.
Abweichungen der produktiven Version werden als verständlicher Fehler angezeigt.

Quellen:
- https://github.com/Team-Nifty-GmbH/flux-core/blob/main/routes/api.php
- https://github.com/Team-Nifty-GmbH/flux-core/blob/main/src/Rulesets/LedgerAccount/CreateLedgerAccountRuleset.php
- https://github.com/Team-Nifty-GmbH/flux-core/blob/main/src/Actions/LedgerAccount/CreateLedgerAccount.php

## SKR04 und spätere Rechnungszuordnung

Diese Grundlage importiert nicht automatisch den vollständigen DATEV-Kontenrahmen
und erfindet keine Kontonummern. Der konkrete Kontensatz wird anschließend aus
einer geprüften SKR04-Quelle übernommen. Kontonummern, Funktionen und Steuersätze
werden nicht aus einer bloßen Bezeichnung abgeleitet.

Die Rechnungsintegration kann später die gespeicherten Flux-IDs verwenden.
Zuordnungsregeln für Lieferanten, Leistungen, Reverse Charge oder Vorsteuer sind
ein eigener fachlicher Schritt. Die vorhandene Nuxbe-Anweisung
`wls-skr04-rechnungszuordnung` wird erst nach tatsächlich erfolgter Kontenanlage
um bestätigte IDs ergänzt.

## Fehlerfälle und Tests

Pest-Tests mit `Http::fake` und der projektüblichen MariaDB-Testdatenbank prüfen:

- verschlüsselte Speicherung, kein Token in UI oder Änderungshistorie;
- Erhaltung des Tokens bei leerer Eingabe und vollständiges Entfernen;
- lesenden Verbindungstest, fehlenden Zugang, 401/403, 422, 429 und Timeout;
- Token-Header, Mandantenzuordnung und vollständige Pagination;
- Abgleich, passende vorhandene Konten, Typkonflikte und Dubletten;
- Anlagepayload, bestätigte externe ID, unklare POST-Antwort;
- Zugriffsschutz und deutsche Rückmeldungen in Livewire.

Abschlussprüfungen: betroffene Tests, bestehende Registrar-Integrationstests,
Laravel Pint und Frontend-Build. Keine produktiven Schreibtests ohne eingerichteten
Zugang und konkreten Kontensatz. Deployment erfolgt separat nach geprüfter Änderung.
