# WLS Portal

Interne Verwaltungskonsole für Kunden, Leistungen und Preise.

Ausdrücklich **kein Kundenportal** — die Anwendung wird ausschließlich intern
von wenigen Mitarbeitern genutzt. Oberfläche und Daten sind durchgängig
deutsch, Währung ist ausschließlich EUR.

Der wirtschaftliche Zweck: jederzeit erkennen, welche Leistungen bei welchem
Kunden bestehen, welcher Preis vereinbart wurde und welche Leistungen
möglicherweise nicht oder nicht mehr korrekt abgerechnet werden.

## Stack

Laravel 13 auf PHP 8.4, Livewire 4, TallStackUI 3, Tailwind CSS 4 (Vite),
Laravel Fortify, Laravel Horizon, Pest 4. MySQL beziehungsweise MariaDB als
Datenbank, Redis für Session, Cache und Queue, S3-kompatibler Object Storage
für Dokumente.

## Einrichtung

```bash
composer install
cp .env.example .env
php artisan key:generate

# Datenbank und Redis müssen erreichbar sein.
php artisan migrate --seed

npm install
npm run build
```

Entwicklungsserver:

```bash
composer run dev
```

Der Seeder legt drei interne Benutzer an, jeweils mit dem Passwort
`EntwicklungPasswort1!`:

- `martin.hoffmann@wls.test`
- `sabine.wagner@wls.test`
- `katrin.berger@wls.test`

## Tests

```bash
composer test
```

Die Test-Suite läuft gegen dieselbe Datenbank-Engine wie die Produktion. Die
Zugangsdaten stehen in `phpunit.xml`; die Datenbank `wls_portal_test` muss
existieren.

## Wiederkehrende Aufgaben

Geplante Preisänderungen werden täglich wirksam gesetzt. Dafür muss der
Laravel-Scheduler laufen:

```bash
php artisan schedule:work        # Entwicklung
* * * * * cd /pfad && php artisan schedule:run >> /dev/null 2>&1   # Produktion
```

Queues laufen über Redis und werden von Horizon überwacht:

```bash
php artisan horizon
```

## Dokumentation

- `docs/PROJECT.md` — fachliche Architektur, Datenmodell und
  Architekturentscheidungen
- `docs/BACKLOG.md` — bewusst verschobene Funktionen und offene Rückfragen
- `docs/ANFORDERUNGEN.md` — die zugrunde liegende Anforderungslage
