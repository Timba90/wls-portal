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

### AE-12 — Normalisierung auf Monats- und Jahreswerte
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

---

## 5. Archivierung

- Kunden dürfen nur archiviert werden, wenn keine aktiven Leistungen mehr
  bestehen (`CustomerServiceStatus::Active`).
- Archivierte Kundenleistungen sind vollständig schreibgeschützt — erzwungen im
  Model über einen `saving`-Guard, nicht nur in der Oberfläche.
- Archivierte Datensätze erscheinen nicht in der globalen Suche.
- Es gibt keine endgültige Löschung über die normale Oberfläche.

---

## 6. Sicherheit

- Alle Seiten außer Login und Passwort-Reset sind authentifizierungspflichtig.
- Passwörter: mindestens 12 Zeichen, Groß- und Kleinbuchstaben, Zahl und
  Sonderzeichen. Kein automatischer Ablauf.
- Keine öffentliche Registrierung — Benutzer legen andere Benutzer an.
- Dokumente liegen in privatem Object Storage, ohne öffentliche URLs; der
  Zugriff läuft ausschließlich über die Anwendung.
- Audit-Einträge sind über die Anwendung nicht änder- oder löschbar.
