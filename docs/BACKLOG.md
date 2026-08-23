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
| Webseiten / Projekte           | dito                                                                                         |
| Hosting-Systeme                | —                                                                                            |
| E-Mail-Synchronisierung        | `email_addresses` ist polymorph und indiziert.                                               |
| KI-Funktionen                  | —                                                                                            |
| Komplexe Backup-Oberfläche     | —                                                                                            |

## Innerhalb von Phase 1 bewusst vereinfacht

| Thema                                     | Entscheidung                                                                                                                        |
|-------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| Katalogänderungen übernehmen              | `catalog_snapshot` und `product_id` bleiben erhalten, damit Unterschiede später angezeigt und einzeln übernommen werden können. Die Vergleichs- und Übernahme-Oberfläche fehlt noch. |
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
| Kennzahl „Letzte Aktivität" in der Kundenliste | Wird erst mit der Abrechnungskontrolle sinnvoll befüllbar. |

## Offene fachliche Rückfragen

| Frage                                                                                                     | Aktuelle Annahme                                                                                              |
|-----------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------|
| Sollen einmalige Leistungen (`once`) in Monats- und Jahresumsatz einfließen?                               | Nein — sie sind nicht wiederkehrend und würden die Kennzahlen verfälschen.                                     |
| Was bedeutet ein fehlender Einkaufspreis?                                                                  | `0` Cent, also keine Kosten. Beide Preisspalten sind `NOT NULL DEFAULT 0`.                                     |
| Zählt eine pausierte Leistung zum Soll-Umsatz?                                                             | Nein — nur `aktiv` fließt in Umsatz-, Kosten- und Margenkennzahlen ein.                                        |
| Zählen Leistungen mit „Bewusst nicht abrechnen" zum Soll-Umsatz?                                           | Nein. Sie werden separat ausgewiesen.                                                                          |
