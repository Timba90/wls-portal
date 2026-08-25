# Projektarchitektur

Interne Verwaltungskonsole für Kunden, Leistungen und Preise.
Kein Kundenportal — die Anwendung wird ausschließlich intern genutzt.

Die fachliche Anforderungslage ist in `docs/ANFORDERUNGEN.md` festgehalten.
Bewusst verschobene Funktionen stehen in `docs/BACKLOG.md`.

---

## 1. Technischer Stack

| Bereich            | Technologie                          |
|--------------------|--------------------------------------|
| Framework          | Laravel 13                           |
| PHP                | 8.4                                  |
| Frontend           | Livewire 4, TallStackUI 3, Alpine.js |
| CSS                | Tailwind CSS 4 (Vite 8)              |
| Authentifizierung  | Laravel Fortify                      |
| Datenbank          | MySQL / MariaDB                      |
| Session/Cache/Queue| Redis                                |
| Queue-Monitoring   | Laravel Horizon                      |
| Dateiablage        | S3-kompatibler Object Storage        |
| Tests              | Pest 4                               |
| Formatierung       | Laravel Pint                         |

Währung ausschließlich EUR, Oberfläche ausschließlich Deutsch.

---

## 2. Codestruktur (modularer Monolith)

Eine Laravel-Anwendung, fachlich nach Bereichen gruppiert. Keine Microservices,
keine Enterprise-Abstraktion.

```
app/
├── Actions/<Bereich>/      Geschäftsvorgänge (CreateCustomer, ArchiveCustomer, …)
├── Enums/                  Feste fachliche Zustände
├── Livewire/<Bereich>/     UI-Komponenten (klassenbasiert)
├── Models/                 Eloquent-Models: Beziehungen, Casts, kleine Helfer
├── Policies/               Zugriffsregeln
├── Support/                Value Objects und technische Helfer
└── Providers/
```

Fachbereiche: `Auth`, `Customers`, `Contacts`, `Catalog`, `Services`, `Pricing`,
`Notes`, `Documents`, `CustomFields`, `Tags`, `Audit`, `Search`.

### Regeln

- **Keine Geschäftslogik in Livewire-Komponenten.** Livewire hält UI-State,
  validiert Formulare und ruft Actions auf.
- **Keine riesigen Models.** Models enthalten Beziehungen, Casts und kleine
  fachliche Helfer. Alles Mehrschrittige gehört in eine Action.
- **Keine Geldbeträge als Float.** Geld immer als Integer in Cent
  (`4999` = 49,99 EUR), gekapselt im Value Object `App\Support\Money`.
- **Keine Hard Deletes für Geschäftsdaten.** Kunden, Leistungen, Kontakte und
  Katalogartikel werden über `archived_at` archiviert.

### Livewire-Konvention

Dieses Projekt verwendet **klassenbasierte Livewire-Komponenten**
(`php artisan make:livewire … --class`) unter `app/Livewire/<Bereich>/` mit Views
unter `resources/views/livewire/<bereich>/`. Grund: die Komponenten sind nach
Fachbereichen gruppiert, werden getestet und referenzieren Actions — eine
klassische Klassendatei passt hier besser als Single-File-Komponenten.

---

## 3. Datenmodell Phase 1

### 3.1 Überblick

```
User ─┬─< Customer (responsible_user_id)
      ├─< CustomerService (responsible_user_id)
      ├─< PriceChange, Note, Document, DocumentVersion, AuditLog
      └─< UserSession

Customer ─┬─< EmailAddress      (polymorph, nur Privatkunden)
          ├─< PhoneNumber       (polymorph, nur Privatkunden)
          ├─< ContactAssignment >─ Contact
          ├─< ContactDeputy
          ├─< CustomerService
          ├─< Note / Document / CustomFieldValue / Tag (polymorph)

Contact ─┬─< EmailAddress (polymorph)
         ├─< PhoneNumber  (polymorph)
         └─< ContactAssignment >─┬─ Customer
                                 └─< ContactRole (Pivot)

Category ─< Category (eine Unterebene)
Product ─┬─< ProductVariant
         ├─< ServiceComponent (polymorph)
         └─< CustomerService

CustomerService ─┬─< ServiceComponent (polymorph)
                 ├─< PriceChange
                 └─< Note / Document / CustomFieldValue / Tag (polymorph)

CustomFieldDefinition ─< CustomFieldValue (polymorph)
Tag ─< Taggable (polymorph)
```

### 3.2 Tabellen

#### `users`
Interne Benutzer. Keine öffentliche Registrierung, keine Rollen — alle Benutzer
haben dieselben Rechte. Zusätzlich zu den Fortify-Spalten
(`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`).

#### `user_sessions`
Eigene Sitzungsverfolgung für die Sessionverwaltung. Notwendig, weil der
Redis-Session-Treiber die Sitzungen eines Benutzers nicht auflisten kann.
`id` ist die Laravel-Session-ID.

| Spalte        | Typ        | Hinweis                     |
|---------------|------------|-----------------------------|
| id            | string PK  | Session-ID                  |
| user_id       | FK users   | cascade                     |
| ip_address    | string     |                             |
| user_agent    | text       |                             |
| last_activity | integer    | Index                       |

#### `sequences`
Transaktionssichere Nummernvergabe (`SELECT … FOR UPDATE`).

| Spalte     | Typ            | Hinweis                       |
|------------|----------------|-------------------------------|
| key        | string, unique | z. B. `customer_number`       |
| next_value | unsigned big   | nächster zu vergebender Wert  |

Eine eigene Tabelle statt `MAX(customer_number) + 1`, damit archivierte oder
gelöschte Nummern **niemals** erneut vergeben werden.

#### `customers`
Ein gemeinsames Tabellenschema für Firmen- und Privatkunden
(Single-Table-Inheritance über `type`).

| Spalte              | Typ                  | Hinweis                                    |
|---------------------|----------------------|--------------------------------------------|
| customer_number     | string(20), unique   | `KD-00001`, unveränderlich                 |
| type                | string               | `CustomerType`: company \| private         |
| company_name        | string, nullable     | Pflicht bei Firmenkunden                   |
| salutation          | string, nullable     | `Salutation`, Privatkunden                 |
| academic_title      | string, nullable     |                                            |
| first_name          | string, nullable     | Pflicht bei Privatkunden                   |
| last_name           | string, nullable     | Pflicht bei Privatkunden                   |
| birth_date          | date, nullable       |                                            |
| gender              | string, nullable     | `Gender`: male \| female \| diverse        |
| short_label         | string               | Kurzbezeichnung, nicht eindeutig           |
| internal_code       | string(32)           | Kürzel, nicht eindeutig                    |
| status              | string               | `CustomerStatus`: active \| archived       |
| responsible_user_id | FK users, nullable   | nullOnDelete                               |
| archived_at         | timestamp, nullable  |                                            |

Indizes: `customer_number` (unique), `status`, `type`, `company_name`,
`last_name`, `short_label`, `internal_code`, `responsible_user_id`.

Bewusst **nicht** gespeichert: Rechtsform, USt-ID, Steuernummer, Branche,
allgemeine E-Mail/Telefon, Homepage, Rechnungsbezeichnung, Anschrift. Diese
Daten kommen später aus dem ERP.

#### `contacts`
Ansprechpartner-Stammdaten. Ohne Firmenfeld — die Zuordnung erfolgt
ausschließlich über `contact_assignments`.

| Spalte                   | Typ                 | Hinweis                       |
|--------------------------|---------------------|-------------------------------|
| salutation               | string, nullable    | `Salutation`                  |
| academic_title           | string, nullable    |                               |
| first_name / last_name   | string              |                               |
| gender                   | string, nullable    | `Gender`                      |
| birth_date               | date, nullable      |                               |
| preferred_contact_method | string, nullable    | `ContactMethod`               |
| archived_at              | timestamp, nullable |                               |

#### `contact_roles`
Frei definierbare Rollen (Geschäftsführung, Technik, Buchhaltung, …).
`name` unique, `sort_order`, `is_active`.

#### `contact_assignments`
Zuordnung eines Ansprechpartners zu einem Firmenkunden. Eigenes Model, weil
Rollen, Priorität, Primärkontakte und Aktiv-Status je Zuordnung gelten.

| Spalte                   | Typ                          | Hinweis                          |
|--------------------------|------------------------------|----------------------------------|
| contact_id               | FK contacts                  | cascade                          |
| customer_id              | FK customers                 | cascade                          |
| is_primary_contact       | boolean                      | mehrere erlaubt                  |
| is_billing_contact       | boolean                      |                                  |
| priority                 | unsigned smallint            | kleiner = wichtiger              |
| is_active                | boolean                      |                                  |
| preferred_contact_method | string, nullable             | überschreibt den Kontakt-Default |
| primary_email_id         | FK email_addresses, nullable | je Zuordnung abweichend möglich  |
| primary_phone_id         | FK phone_numbers, nullable   | je Zuordnung abweichend möglich  |

Unique: `(contact_id, customer_id)`.

#### `contact_assignment_role`
Pivot `contact_assignment_id` × `contact_role_id`, unique kombiniert.
Ein Ansprechpartner kann je Kunde mehrere Rollen haben.

#### `contact_deputies`
Vertretungen je Kunde und Rolle, mit Priorität.
Unique: `(customer_id, contact_role_id, contact_id)`.

#### `email_addresses` / `phone_numbers`
Polymorphe Kontaktkanäle für `Customer` (Privatkunden) und `Contact`.

| Spalte      | Typ               | Hinweis                                 |
|-------------|-------------------|-----------------------------------------|
| owner_type  | string            | morph                                   |
| owner_id    | big int           | morph                                   |
| email/number| string            | `email` zusätzlich indiziert (Suche)    |
| type        | string            | `ContactChannelType`                    |
| is_primary  | boolean           | genau eine je Owner                     |
| sort_order  | unsigned smallint |                                         |

`is_primary` wird in einer Transaktion durch die jeweilige Action erzwungen —
MySQL kennt keine partiellen Unique-Indizes.

#### `categories`
Eine Hierarchiestufe: Kategorie + Unterkategorie.
`parent_id` (nullable, self-FK, cascade), `name`, `sort_order`, `is_active`.
Unique: `(parent_id, name)`. Eine Kategorie mit `parent_id` darf selbst keine
Kinder haben — geprüft in `SaveCategory`.

#### `tags` / `taggables`
Generisches, polymorphes Tag-System. `tags.name` unique.
`taggables`: `tag_id` × `(taggable_type, taggable_id)`, unique kombiniert.
Nutzbar für Kunden, Ansprechpartner, Katalogartikel und Kundenleistungen; ohne
Migration erweiterbar auf Projekte und Domains.

#### `products` (Artikel / Leistung)
Zentraler Katalog. Fachlich intern `Product`, in der Oberfläche
„Artikel / Leistung".

| Spalte                           | Typ                 | Hinweis                          |
|----------------------------------|---------------------|----------------------------------|
| name / internal_name             | string              | internal_name indiziert          |
| description                      | text, nullable      |                                  |
| category_id / subcategory_id     | FK categories       | nullOnDelete                     |
| status                           | string              | `CatalogStatus`                  |
| default_purchase_price_cents     | integer             | Standard-Einkaufspreis           |
| default_sales_price_cents        | integer             | Standard-Verkaufspreis           |
| default_billing_interval_unit    | string              | `BillingIntervalUnit`            |
| default_billing_interval_count   | unsigned smallint   | `null` bei `once`                |
| archived_at                      | timestamp, nullable |                                  |

#### `product_variants`
Varianten eines Katalogartikels (Basic / Business / Premium).
Preis- und Intervallspalten sind **nullable**: `null` bedeutet „erbt vom
Katalogartikel". `sort_order`, `status`, `archived_at`.

#### `service_components`
Leistungsbestandteile — **eine** polymorphe Tabelle für `Product`,
`ProductVariant` und `CustomerService`.

| Spalte                                   | Typ                | Hinweis                |
|------------------------------------------|--------------------|------------------------|
| componentable_type / componentable_id    | morph              | Index inkl. sort_order |
| title                                    | string             |                        |
| description                              | text, nullable     |                        |
| sort_order                               | unsigned smallint  |                        |
| purchase_price_cents / sales_price_cents | integer, nullable  | optional               |

#### `customer_services` — zentrales Modell
Die tatsächlich bei einem Kunden bestehende Leistung. Eigenes Model, keine
Pivot-Tabelle. Kann **ohne** Katalogartikel existieren (vollständig individuelle
Leistung).

| Spalte                    | Typ                       | Hinweis                                    |
|---------------------------|---------------------------|--------------------------------------------|
| customer_id               | FK customers              | restrictOnDelete                           |
| product_id                | FK products, nullable     | Herkunft aus dem Katalog                   |
| product_variant_id        | FK variants, nullable     |                                            |
| catalog_snapshot          | json, nullable            | Katalogwerte zum Verknüpfungszeitpunkt     |
| name                      | string                    | interner Anzeigename                       |
| billing_label             | string, nullable          | ERP-/Rechnungsbezeichnung                  |
| description               | text, nullable            |                                            |
| status                    | string                    | `CustomerServiceStatus`                    |
| purchase_price_cents      | integer, default 0        | Einkaufspreis in Cent                      |
| sales_price_cents         | integer, default 0        | Verkaufspreis in Cent                      |
| billing_interval_unit     | string                    | `BillingIntervalUnit`                      |
| billing_interval_count    | unsigned smallint, null   | `null` bei `once`                          |
| service_start_date        | date, nullable            | Leistungsbeginn                            |
| billing_start_date        | date, nullable            | separates Abrechnungsstartdatum            |
| first_billing_date        | date, nullable            | geplantes erstes Abrechnungsdatum          |
| category_id/subcategory_id| FK categories, nullable   |                                            |
| responsible_user_id       | FK users, nullable        |                                            |
| do_not_bill               | boolean, default false    | „Bewusst nicht abrechnen"                  |
| do_not_bill_reason        | string, nullable          | `DoNotBillReason`                          |
| do_not_bill_since         | timestamp, nullable       | gesetzt beim Aktivieren                    |
| do_not_bill_released_at   | timestamp, nullable       | gesetzt beim Entfernen                     |
| archived_at               | timestamp, nullable       | archiviert ⇒ read-only                     |

Indizes: `(customer_id, status)`, `product_id`, `status`, `do_not_bill`,
`billing_interval_unit`, `responsible_user_id`.

#### `price_changes`
Preisverlauf je Kundenleistung. Preise werden nie stillschweigend überschrieben.

| Spalte              | Typ                    | Hinweis                                   |
|---------------------|------------------------|-------------------------------------------|
| customer_service_id | FK, cascade            |                                           |
| price_type          | string                 | `PriceType`: sales \| purchase            |
| old_price_cents     | integer, nullable      | `null` beim Anlegen                       |
| new_price_cents     | integer                |                                           |
| effective_date      | date                   | nie in der Vergangenheit                  |
| applied_at          | timestamp, nullable    | `null` = geplant, gesetzt = wirksam       |
| user_id             | FK users, nullable     |                                           |
| note                | string, nullable       |                                           |

Indizes: `(customer_service_id, price_type, effective_date)`,
`(effective_date, applied_at)`.

#### `custom_field_definitions` / `custom_field_values`
Generisches Custom-Field-System.
Definition: `entity_type` (`CustomFieldEntity`), `key`, `name`, `type`
(`CustomFieldType`), `is_required`, `default_value`, `options` (json),
`sort_order`, `is_active`, `visibility_condition` (json, für spätere bedingte
Sichtbarkeit vorgesehen). Unique: `(entity_type, key)`.
Wert: `custom_field_definition_id` × `(customizable_type, customizable_id)`,
unique kombiniert, `value` als JSON (deckt auch Mehrfachauswahl ab).

#### `notes`
Polymorphe Notizen. `notable_*`, `user_id`, `category` (`NoteCategory`), `body`.
Index `(notable_type, notable_id, created_at)`.

#### `documents` / `document_versions`
Polymorphe Dokumente mit Versionierung. Eine neue Datei ersetzt die alte Version
niemals physisch; die höchste `version` ist automatisch die aktuelle.
`document_versions`: `disk`, `path` (S3-Key), `original_filename`, `mime_type`,
`size_bytes`, `checksum`, `uploaded_by`. Unique: `(document_id, version)`.

#### `audit_logs`
Vollständige Änderungshistorie. Nur `created_at`, kein `updated_at` — Einträge
sind unveränderlich und über die Anwendung nicht löschbar.
`user_id`, `auditable_*`, `event`, `old_values` (json), `new_values` (json),
`description`, `ip_address`.

#### `table_configurations`
Globale (nicht benutzerspezifische) Tabellenkonfiguration:
`table_key` unique, `columns` (json mit Sichtbarkeit, Reihenfolge, Breite).

#### `project_types`
Frei definierbare Projekttypen (§61): `name` unique, `short_label`, `icon`,
`color`, `sort_order`, `is_active`. Fest angelegt sind Laravel, Shopify und
WordPress; `icon` benennt das Markenzeichen, das
`resources/views/components/project-type-icon.blade.php` zeichnet.

#### `projects`
`project_number` unique und unveränderlich, `name`, `description`,
`customer_id` (restrict), `project_type_id` / `responsible_user_id` (null on
delete), `status`, `start_date`, `deadline`, `risk_note`, `archived_at`.
Betrieb: `backup_status`, `security_status`, `update_status`
(`App\Enums\OperationsStatus`, Voreinstellung `unknown`) und
`operations_checked_on`. Die drei Ampeln werden von Hand gepflegt — es gibt
keine Überwachung, die sie speisen könnte.

#### `project_milestones`
`project_id` (cascade), `name`, `note`, `status`, `due_date`, `sort_order`.
Grundlage des Fortschritts.

#### `project_positions`
`project_id` (cascade), optional `product_id` / `customer_service_id` (null on
delete), `name`, `kind` (einmalig / wiederkehrend), `quantity`,
`unit_price_cents`, `status`, `sort_order`. Name und Preis liegen immer auf der
Position selbst.

#### `project_members`
`project_id`, `user_id`, `role` (Freitext), `sort_order`; eindeutig je
Projekt und Person.

---

## Umsetzungsstand

| Meilenstein | Inhalt | Stand |
|-------------|--------|-------|
| 1 | Projektbasis, Authentifizierung, Basislayout | umgesetzt |
| 2 | Kunden, Kundennummer, Liste, Detailseite, Archivierung | umgesetzt |
| 3 | Ansprechpartner, Rollen, Vertretungen | umgesetzt |
| 4 | Katalog, Kategorien, Varianten, Tags | umgesetzt |
| 5 | Kundenleistungen inkl. Kennzahlen | umgesetzt |
| 6 | Preisverlauf, geplante Preisänderungen | umgesetzt |
| 7 | Notizen, Dokumente, Custom Fields, Audit Log | umgesetzt |
| 8 | Dashboard, globale Suche, Leistungsübersicht, Archiv | umgesetzt |
| — | Projekte: Liste, Detail, Meilensteine, Positionen, Team, Projekttypen | umgesetzt (§61 vorgezogen, siehe AE-17) |
| — | Katalogabgleich: Änderungen am Katalog sichtbar machen und einzeln übernehmen | umgesetzt (siehe AE-18) |

Die Kennzahlen der Kundenliste (Anzahl aktiver Leistungen, Monats- und
Jahresumsatz, Kosten, Marge) werden aus den Kundenleistungen berechnet. In die
Summen fließen ausschließlich aktive, wiederkehrende Leistungen ohne die
Kennzeichnung „Bewusst nicht abrechnen" ein.

---

## 4. Architekturentscheidungen

### AE-1 — Kundennummern über eine Sequenztabelle
Statt `MAX(customer_number) + 1` gibt es die Tabelle `sequences`. Der nächste
Wert wird in einer Transaktion mit `lockForUpdate()` gelesen und erhöht. Damit
sind Race Conditions ausgeschlossen und eine einmal vergebene Nummer wird auch
dann nicht erneut vergeben, wenn der Kunde archiviert wird.
Die Nummer ist nach der Erstellung unveränderlich — erzwungen im Model, nicht
nur in der Oberfläche.

### AE-2 — Ein gemeinsames `customers`-Schema für beide Kundentypen
Firmen- und Privatkunden teilen sich eine Tabelle mit `type`-Diskriminator.
Grund: gemeinsame Kundennummer, gemeinsame Liste, gemeinsame Leistungen,
Notizen, Dokumente und Tags. Getrennte Tabellen würden jede dieser Beziehungen
verdoppeln.
**Kosten:** Die Datenbank kann typabhängige Pflichtfelder nicht erzwingen. Das
übernehmen die Actions, Form-Requests und Tests.

### AE-3 — Enums als `string`-Spalten, nicht als MySQL-`ENUM`
Alle fachlichen Zustände sind PHP-Enums (`CustomerStatus`, `Gender`,
`BillingIntervalUnit`, …) und werden als `string` gespeichert. Neue Werte —
etwa weitere Notizkategorien — benötigen dadurch keine Migration.

### AE-4 — Eine polymorphe Tabelle für Leistungsbestandteile
Die Anforderung nennt `ServiceComponent` und `CustomerServiceComponent`
getrennt. Umgesetzt ist eine polymorphe Tabelle `service_components` für
Katalogartikel, Varianten und Kundenleistungen. Die Struktur ist identisch, das
Verhalten ebenfalls — zwei Tabellen wären reine Duplikation.

### AE-5a — Modals brauchen `wire` und eine eigene `id`
TallStackUI bindet ein Modal über das Attribut `wire="eigenschaft"`, nicht über
`wire:model`. Ohne `wire` bleibt das Modal dauerhaft geschlossen. Zusätzlich
trägt jedes Modal eine eigene `id` — die Vorgabe `modal` kollidiert, sobald
zwei Modals auf derselben Seite stehen.

### AE-5 — Aktueller Preis denormalisiert, Historie vollständig
`customer_services` trägt den aktuell gültigen Preis, `price_changes` die
vollständige Historie inklusive geplanter zukünftiger Änderungen. Grund: Listen
und Dashboard-Kennzahlen brauchen den aktuellen Preis ohne Unterabfrage.
**Kosten:** Beide müssen konsistent bleiben. Preisänderungen laufen deshalb
ausschließlich über `App\Actions\Pricing\*`; direkte Zuweisungen der
Preisspalten sind außerhalb dieser Actions nicht vorgesehen.

### AE-6 — Katalog-Snapshot auf der Kundenleistung
Wird eine Kundenleistung aus einem Katalogartikel erzeugt, speichert
`catalog_snapshot` die Katalogwerte des Verknüpfungszeitpunkts. Damit lässt sich
später unterscheiden zwischen „der Kunde weicht bewusst ab" und „der Katalog hat
sich seitdem geändert". Bestehende Kundenleistungen werden bei Katalogänderungen
**niemals** automatisch verändert.

### AE-7 — Kategorie und Unterkategorie als zwei Fremdschlüssel
`category_id` und `subcategory_id` zeigen beide auf `categories`. Alternativ
hätte ein einzelner Fremdschlüssel genügt (die Elternkategorie wäre ableitbar),
die Anforderung nennt aber ausdrücklich zwei Felder.
**Kosten:** Die Kombination kann inkonsistent werden. Validiert wird, dass
`subcategory.parent_id === category_id`.

### AE-8 — Sessionverwaltung trotz Redis-Sessions
Der Redis-Session-Treiber kann die Sitzungen eines Benutzers nicht auflisten.
Die Tabelle `user_sessions` protokolliert deshalb Session-ID, IP, User-Agent und
letzte Aktivität. Das Beenden einer Sitzung löscht sie über den Session-Handler
direkt aus Redis und entfernt die Zeile.

### AE-9 — Automatischer Logout nach 30 Minuten
Serverseitig über `SESSION_LIFETIME=30`; Laravel verlängert die Sitzung bei
jedem Request. Ergänzend beendet ein kleiner Alpine-Timer die Ansicht im
Browser, damit kein veralteter Bildschirm stehen bleibt.

### AE-10 — 2FA optional, global erzwingbar vorbereitet
2FA ist pro Benutzer optional. Die Konfiguration `auth.two_factor_required`
(Standard `false`) und die Middleware `EnsureTwoFactorIsEnabled` erlauben es
später, 2FA global verpflichtend zu machen, ohne das Datenmodell zu ändern.

### AE-11 — Einkaufs- und Verkaufspreis mit Default 0
Beide Preisspalten sind `NOT NULL DEFAULT 0` statt nullable. Grund: Marge,
Deckungsbeitrag und alle Dashboard-Summen wären sonst durchgehend
null-behaftet. `0` bedeutet fachlich „keine Kosten / kein Erlös" — etwa bei
Eigenleistung oder Inklusivleistungen.

### AE-12 — Eine Kontaktkanal-Tabelle je Art, polymorph
`email_addresses` und `phone_numbers` haengen polymorph an `Customer`
(Privatkunden) und `Contact`. Getrennte Tabellen je Besitzertyp waeren reine
Duplikation. `is_primary` wird von `SyncContactChannels` in einer Transaktion
erzwungen: die erste als primaer markierte Zeile gewinnt, ist keine markiert,
wird es die erste Zeile.

### AE-13 — Tabellenkonfiguration global im Trait
`App\Livewire\Concerns\WithConfigurableTable` haelt sichtbare Spalten,
Reihenfolge und Breite in `table_configurations` — je Tabellenschluessel eine
Zeile, gueltig fuer alle Benutzer. Gespeicherte Konfigurationen werden beim
Laden mit den aktuellen Spaltendefinitionen abgeglichen: neue Spalten kommen
hinzu, entfallene verschwinden. Als `fixed` markierte Spalten
(Kundennummer, Name) lassen sich nicht ausblenden.

### AE-12a — Visuelles System aus dem Entwurf „WLS Portal"
Farben, Schriften und Flächen stammen aus dem Design-Canvas `WLS Portal.dc.html`:
Akzent `#4ADE9B`, Flächen von `#0C1013` bis `#1B2126`, Textstufen `#F0F3F3` bis
`#606C73`, Statuspillen in fünf Ausprägungen, IBM Plex Mono für Marke, Labels
und Zahlen sowie Source Sans 3 für Fließtext.

Der Entwurf ist ausschließlich dunkel gehalten. Die Anforderung verlangt jedoch
beide Erscheinungsbilder (§42). Die Tokens sind deshalb semantisch benannt
(`--surface-*`, `--ink-*`, `--accent*`, `--pill-*`) und werden für den hellen
Modus umdefiniert — die Oberfläche selbst bleibt in beiden Fällen unverändert.

TallStackUI wird nicht ersetzt, sondern umgefärbt (§41): die Tokens `primary-*`
und `dark-*` sind auf die Palette des Entwurfs gemappt, und die Schaltflächen
tragen über `App\View\Components\TallStackUi\Colors\NormalButtonColors` die
Akzentfarbe mit dunkler Schrift — das Paket würde im Dark Mode sonst einen
dunkleren Ton mit heller Schrift verwenden.

### AE-13a — Kennzahlen in PHP statt in SQL
Umsatz, Kosten und Marge entstehen durch Normalisierung unterschiedlicher
Abrechnungsintervalle auf einen Monatswert. In SQL wäre das eine unübersichtliche
CASE-Konstruktion; stattdessen laufen die Summen in PHP über `chunkById` und nur
über die abrechnungsrelevanten Spalten. Bei der erwarteten Datenmenge — wenige
tausend Kundenleistungen — ist das schnell genug und deutlich besser prüfbar.

### AE-14 — Normalisierung auf Monats- und Jahreswerte
`App\Support\BillingInterval` rechnet jeden Betrag auf einen Monatswert um:

| Einheit | Monatsfaktor            |
|---------|-------------------------|
| `once`  | — (nicht wiederkehrend) |
| `day`   | 365,25 / 12 / count     |
| `week`  | 365,25 / 7 / 12 / count |
| `month` | 1 / count               |
| `year`  | 1 / (12 × count)        |

Der Jahreswert ist stets `Monatswert × 12`. Gerundet wird kaufmännisch auf
ganze Cent. Einmalige Leistungen (`once`) fließen **nicht** in Monats- und
Jahresumsatz ein.

### AE-15 — MCP-Server mit vollen Schreibrechten
Der Datenbestand ist über einen MCP-Server für KI-Clients erreichbar
(`app/Mcp`, Route `mcp/portal`, 36 Werkzeuge). Der Auftraggeber hat sich
ausdrücklich für den vollen Umfang **ohne Leitplanken** entschieden: neben
Lesen und Schreiben auch endgültiges Löschen und das direkte Überschreiben von
Preisen am Preisverlauf vorbei. Das steht bewusst quer zu den Grundsätzen
„keine Hard Deletes" und „Preise werden nie stillschweigend überschrieben",
die für die Oberfläche unverändert gelten.

Abgesichert ist der Zugang über persönliche Sanctum-Tokens. Ein Token trägt
die vollen Rechte seines Benutzers, ist einzeln widerrufbar und läuft
standardmäßig nach 90 Tagen ab. Es gibt keine feinere Rechteabstufung — wer
ein Token hat, kann alles, was der Benutzer kann.

Vier Dinge bleiben trotzdem bestehen, weil sie im Model sitzen und nicht in
den Actions:

- **Die Änderungshistorie.** `Auditable` hängt an den Model-Events, nicht an
  den Actions. Auch ein Schreibzugriff an den Actions vorbei landet dort, und
  Audit-Einträge bleiben unveränderlich und unlöschbar. Nach einem endgültigen
  Löschen ist die Historie das einzige, was den Vorgang noch belegt.
- **Der Schreibschutz archivierter Kundenleistungen.**
- **Die Unveränderlichkeit der Kundennummer.**
- **Das Verbot rückwirkender Preisänderungen** — es gilt für
  `preisaenderung-planen`; `preis-direkt-setzen` umgeht es naturgemäß, weil es
  gar keinen Verlaufseintrag schreibt.

Die sechs gefährlichen Werkzeuge (`kunde-loeschen`,
`ansprechpartner-loeschen`, `produkt-loeschen`, `leistung-loeschen`,
`projekt-loeschen`, `preis-direkt-setzen`) tragen die MCP-Annotation
`destructiveHint` und verlangen eine inhaltliche Bestätigung — die
Kundennummer, den Nachnamen, den internen Namen, den Leistungsnamen, die
Projektnummer beziehungsweise die Zeichenkette `ohne-preisverlauf`. Das
schützt nicht vor Absicht, aber vor einem falsch aufgelösten Datensatz.

Meilensteine und Projektpositionen fallen bewusst **nicht** darunter: sie sind
Planung, kein Beleg, und werden auch in der Oberfläche endgültig entfernt.

`App\Actions\Maintenance\DeletePermanently` bündelt das Löschen an einer
Stelle. Nötig ist das, weil `customer_services.customer_id` und
`projects.customer_id` auf `restrictOnDelete` stehen und die polymorphen Anhänge — Notizen, Dokumente,
benutzerdefinierte Felder, Tags, Kontaktkanäle — überhaupt keinen
Fremdschlüssel besitzen und sonst als Waisen zurückblieben. Dokumentdateien
werden dabei auch aus dem Object Storage entfernt.

`MCP_ENABLED=false` schaltet den Endpunkt ab, ohne dass Tokens
zurückgezogen werden müssen.

### AE-16 — Markenspalte der Anmeldeseite auf eigenen Tokens
Im Dark Mode standen auf der Anmeldeseite zwei fast gleich dunkle Hälften
nebeneinander — `#101418` für die Formularseite gegen `#14181B` für die
Markenspalte.

**Nachtrag:** Der erste Anlauf nahm die Anmeldeseite ganz aus dem Farbschema
heraus — helle Formularseite, dunkle Markenspalte, unabhängig von der
Einstellung. Auf Wunsch des Auftraggebers folgt die Seite wieder dem
Farbschema. Geblieben sind die eigenen `--brand-*`-Tokens: die Markenspalte
bleibt konstant dunkel und setzt sich durch Rand und Raster vom Formular ab,
statt im Dark Mode mit ihm zu verschmelzen. Der Umschalter steht wieder im Fuß
der Markenspalte. Der Unterschied von drei Helligkeitsstufen war auf einem
gewöhnlichen Bildschirm nicht wahrnehmbar; die Seite las sich als eine große
flache dunkle Fläche ohne die Trennung, die das zweispaltige Layout tragen
soll.

Die Markenspalte ist deshalb jetzt eine **Markenfläche, keine Themafläche**:
sie bleibt konstant dunkel, mit eigenen Tokens (`--brand-*`), die außerhalb von
`:root`/`.dark` definiert sind. Die Formularseite bleibt konstant hell. Erreicht
wird das dadurch, dass das `<html>` des Gast-Layouts die Dark-Mode-Bindung gar
nicht erst trägt — ohne die Klasse `dark` lösen alle Tokens auf ihre hellen
Werte auf, einschließlich der TallStackUI-Formularfelder. Ein zweiter
Token-Satz für die Felder wäre sonst nötig gewesen.

Systemerkennung und manuelle Umschaltung (§42) gelten damit wieder auf allen
Seiten, in der Anmeldung wie in der angemeldeten Oberfläche.

Hinter der Markenspalte liegt ein dekoratives Raster (`resources/js/marken-raster.js`):
ein Canvas, dessen 80-Pixel-Zellen unter dem Mauszeiger aufleuchten und danach
verblassen. Die Animationsschleife läuft nur, solange überhaupt eine Zelle
sichtbar ist, das Canvas nimmt keine Zeigerereignisse an, und bei
`prefers-reduced-motion: reduce` bleibt der Effekt vollständig aus. Die
Leuchtfarbe kommt aus `--brand-accent`, wird also nicht doppelt gepflegt.

### AE-17 — Projekte hängen am Kunden, nicht an der Kundenleistung
§61 führt Projekte als Zukunft. Der Auftraggeber hat die Umsetzung
ausdrücklich freigegeben, solange die Anwendung noch nicht produktiv läuft, und
die offene Strukturfrage zur Entscheidung überlassen.

Ein Projekt hängt an genau einem **Kunden** (`projects.customer_id`,
`restrictOnDelete`), nicht an einer Kundenleistung. Der Grund ist fachlich: ein
Relaunch berührt Hosting, Wartung und Domain gleichzeitig — hinge das Projekt
an einer Leistung, müsste man willkürlich eine davon zur Trägerin erklären. Der
Bezug zu einzelnen Leistungen entsteht stattdessen über die Positionen: eine
Projektposition verweist wahlweise auf einen Katalogartikel, auf eine bestehende
Kundenleistung oder auf nichts. Sie speichert Name und Einzelpreis **immer
selbst**, damit sie lesbar und rechenbar bleibt, wenn der Artikel später
verschwindet — dieselbe Begründung wie beim Katalog-Snapshot (AE-6).

Weitere Festlegungen:

- **Projektnummer** `PR-00001` über dieselbe Sequenztabelle wie die
  Kundennummer (AE-1) und nach der Erstellung unveränderlich, erzwungen im
  Model.
- **Projekttypen** sind eine Tabelle, kein Enum. §61 nennt Webseite, Shop,
  Web-App, API und internes Tool ausdrücklich als Beispiele; die Liste ist frei
  definierbar und über `/projekte/typen` pflegbar.
- **Fortschritt** wird aus den Meilensteinen gerechnet, nicht von Hand gepflegt.
  Ohne Meilensteine ist er `null` und die Oberfläche schreibt „Keine
  Meilensteine" statt einer Prozentzahl ohne Grundlage. Entfallene Meilensteine
  zählen als erledigt — sonst bliebe der Fortschritt dauerhaft unter hundert
  Prozent.
- **Projektvolumen** ist die Summe der *einmaligen* Positionen. Wiederkehrende
  Positionen stehen daneben als Monatsbetrag, weil sie sich nicht auf denselben
  Zeitraum beziehen (dieselbe Trennung wie in AE-14).
- **Status Archiviert** ist im Formular nicht wählbar; er entsteht
  ausschließlich über das Archivieren, damit der Schreibschutz nicht versehentlich
  gesetzt oder aufgehoben wird. Die Reaktivierung bringt das Projekt als
  *pausiert* zurück, nicht als laufend — ob es weiterläuft, entscheidet die
  Person, die es reaktiviert.
- **Meilensteine und Positionen** werden hart gelöscht. Sie sind Planung, kein
  Beleg; der Vorgang steht in der Änderungshistorie. Das Projekt selbst folgt
  weiter der Regel „kein endgültiges Löschen" und wird archiviert.

---

### AE-18 — Katalogänderungen werden gezeigt, nie automatisch übernommen

AE-6 hält fest, dass bestehende Kundenleistungen bei Katalogänderungen niemals
automatisch verändert werden. Bis hierher war das nur die halbe Wahrheit: die
Änderung wurde auch niemandem *gezeigt*. Wer den Listenpreis erhöhte, erfuhr
nirgends, welche laufenden Verträge noch auf dem alten Stand liefen.

Der Vergleich braucht **drei** Stände, nicht zwei:

| Stand | Bedeutung |
|---|---|
| zuletzt gesehen | Der Katalog, über den zuletzt jemand entschieden hat; anfangs der Verknüpfungszeitpunkt. |
| Katalog heute | Was derzeit im Katalog steht. |
| Diese Leistung | Was beim Kunden tatsächlich gilt. |

Erst daraus lassen sich zwei Aussagen trennen, die sonst verschwimmen: „der
Katalog hat sich seither geändert" (Stand ≠ Katalog heute) und „der Kunde weicht
bewusst ab" (Stand ≠ Leistung). Eine Oberfläche, die nur zwei Werte zeigt, kann
den bewusst gewährten Rabatt nicht von der verpassten Preiserhöhung
unterscheiden.

**Der zweite Snapshot.** `catalog_snapshot` bleibt unangetastet der Stand des
Verknüpfungszeitpunkts. Der Abgleich schreibt stattdessen
`catalog_reviewed_snapshot` fort. Ohne diese Trennung hätte entweder AE-6 seine
Bedeutung verloren oder dieselbe Katalogänderung hätte für immer als offen
gegolten — auch nachdem jemand entschieden hat, den Kundenwert bewusst zu
behalten.

**Zwei gleichwertige Ausgänge.** *Übernehmen* setzt den heutigen Katalogwert auf
die Leistung, *Behalten* lässt sie unverändert. Beides schließt den Vorgang ab
und wird je Feld entschieden — wer über den Verkaufspreis entscheidet, hat nicht
über den Einkauf entschieden.

**Übernommene Preise laufen über den Preisverlauf.** Eine übernommene
Katalogerhöhung ist eine Preisänderung wie jede andere und gehört in die
Historie (AE-5), nicht in einen stillen Schreibzugriff.

**Die Bezeichnung wird nicht übernommen.** Die Leistung trägt bewusst einen
eigenen Namen („Hosting Webseite Müller" statt „Managed Hosting"). Dass der
Artikel jetzt anders heißt, wird gezeigt; ändern muss man den Namen selbst.

**Gefiltert wird in PHP.** Der gesehene Stand liegt als JSON vor, die heutigen
Werte hängen an Artikel und Variante — in SQL ist das nicht zu vergleichen. Die
Kandidatenmenge ist eng (nur nicht archivierte Leistungen mit Katalogherkunft)
und wird mit ihren Beziehungen in einem Zug geladen. Dieselbe Linie wie AE-13a.

---

## 5. Archivierung

- Kunden dürfen nur archiviert werden, wenn keine aktiven Leistungen mehr
  bestehen (`CustomerServiceStatus::Active`).
- Archivierte Kundenleistungen sind vollständig schreibgeschützt — erzwungen im
  Model über einen `saving`-Guard, nicht nur in der Oberfläche.
- Archivierte Datensätze erscheinen nicht in der globalen Suche.
- Es gibt keine endgültige Löschung über die normale Oberfläche.

---

## 5a. Kennzahlen

In Umsatz, Kosten und Marge fließen ausschließlich Leistungen ein, die

- den Status `aktiv` tragen,
- **nicht** als „Bewusst nicht abrechnen" gekennzeichnet sind und
- ein wiederkehrendes Abrechnungsintervall haben.

Einmalige Leistungen (`once`) wiederholen sich nicht und würden Monats- und
Jahreswerte verfälschen. Dashboard und Leistungsübersicht weisen die
ausgenommenen Leistungen deshalb separat aus.

---

## 6. Sicherheit

- Alle Seiten außer Login und Passwort-Reset sind authentifizierungspflichtig.
- Passwörter: mindestens 12 Zeichen, Groß- und Kleinbuchstaben, Zahl und
  Sonderzeichen. Kein automatischer Ablauf.
- Keine öffentliche Registrierung — Benutzer legen andere Benutzer an.
- Dokumente liegen in privatem Object Storage, ohne öffentliche URLs; der
  Zugriff läuft ausschließlich über die Anwendung.
- Audit-Einträge sind über die Anwendung nicht änder- oder löschbar.
