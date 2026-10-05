# Ausführung und Prüfbefunde

Die vier Umsetzungsschritte sind abgeschlossen: verschlüsselter Zugang/REST-Client, Sachkontenzuordnungen/Anlage, Portaloberfläche und Dokumentation. Die Verhaltenstests wurden vor der jeweiligen Implementierung mit erwarteten fehlenden Klassen bzw. Komponenten ausgeführt.

## Entscheidungen und Abweichungen

- Windows: Composer mit ignorierten Horizon-Plattformanforderungen ext-pcntl/ext-posix installiert; keine Änderungen am Lockfile. Diese Unix-Erweiterungen bleiben für den produktiven Horizon-Betrieb erforderlich.
- Dedizierte lokale MySQL-8-Datenbank `wls_flux_rest_test_20261005` statt MariaDB bzw. des vorgeschlagenen Datenbanknamens verwendet. Keine Produktivdaten und kein SQLite.
- HTTP-Vertragstests unter Unit, Datenbank-/Oberflächentests unter Feature.
- Persistenzhelfer `FluxLedgerAccount::remember` bleibt als gemeinsame kleine Modellfunktion für beide Actions bestehen; die fachlichen Abläufe liegen in Actions.
- UI-Methodenname `create` und Route `ledger-accounts.index` statt der geplanten Namen; alle Aufrufer sind konsistent.
- Zugangvalidierung im zentralen Client statt doppeltem Laravel-Validator; sichere Feldprüfung und Fehlermeldung gelten beim Speichern sowie Lesen.

## Unabhängige Prüfung

- Formular-/Modelltypnamen waren inkonsistent: korrigiert und durch tatsächliche Livewire-Kontoanlage samt Tabellenrendering abgesichert.
- P2: 120-Sekunden-Lock konnte bei langsamer Pagination vor dem POST ablaufen. Vorprüfung auf 60 Sekunden begrenzt, vor/nach jedem Listenabruf sowie vor dem POST geprüft. POST-Timeout beträgt 20 Sekunden. Abgelaufene Vorprüfung sendet keine Anfrage; Regressionstest ergänzt.
- Minor: Modellpersistenzhelfer bewusst beibehalten, siehe oben.
- Minor: Unit-Verzeichnis für HTTP-only-Tests bewusst beibehalten.

Keine produktiven REST-Schreibanfragen, Veröffentlichung oder E-Mail-Versendung ausgeführt. Produktiver Zugang und Migration sind nach Deployment zu prüfen.

## Abschlussvalidierung

- Flux-Unit-/Feature-Tests plus bestehende Registrar-Zugangstests: 40 bestanden, 86 Assertions.
- `php vendor/bin/pint --test`: bestanden.
- `npm run build`: bestanden; lediglich optionaler Fontaine-Hinweis.
- `git diff --check`: bestanden.

Die gezielten Tests decken Tokenverschlüsselung/-maskierung, Hostbegrenzung, Pagination, Fehlerbehandlung, Mandantenfilter, Typkonflikt, verlorene POST-Antwort, Formularanlage und Gast-/Providerzugriffe ab. Ein produktiver Smoke-Test und ein Mehrprozess-Lasttest wurden nicht ausgeführt.
