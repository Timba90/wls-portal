<?php

use App\Livewire\Catalog\ProductList;
use App\Livewire\Contacts\ContactList;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Services\ServiceOverview;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPosition;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Zaehlt die Datenbankabfragen beim Aufbau einer Komponente.
 */
function abfragenBeimAufbau(string $komponente, User $benutzer): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($benutzer)->test($komponente)->html();

    $anzahl = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $anzahl;
}

dataset('listen', [
    'Kundenliste' => [
        CustomerList::class,
        function (int $anzahl): void {
            Customer::factory()->count($anzahl)->create()->each(
                fn (Customer $kunde) => CustomerService::factory()->count(2)->for($kunde)->create(),
            );
        },
    ],
    'Artikelkatalog' => [
        ProductList::class,
        fn (int $anzahl) => Product::factory()->count($anzahl)->create(),
    ],
    'Leistungsübersicht' => [
        ServiceOverview::class,
        fn (int $anzahl) => CustomerService::factory()->count($anzahl)->create(),
    ],
    'Ansprechpartnerliste' => [
        ContactList::class,
        function (int $anzahl): void {
            $kunde = Customer::factory()->company()->create();

            Contact::factory()->count($anzahl)->create()->each(
                fn (Contact $kontakt) => $kontakt->assignments()->create(['customer_id' => $kunde->id]),
            );
        },
    ],
    'Projektliste' => [
        ProjectList::class,
        function (int $anzahl): void {
            // Ein gemeinsamer Typ: die Factory zieht sonst je Projekt einen
            // eindeutigen Namen und geht Faker aus.
            $typ = ProjectType::factory()->create();

            Project::factory()->count($anzahl)->for($typ)->create()->each(function (Project $projekt): void {
                ProjectMilestone::factory()->count(2)->for($projekt)->create();
                ProjectPosition::factory()->count(2)->for($projekt)->create();
            });
        },
    ],
]);

/**
 * Der Wächter gegen N+1. Es geht um die Steigung, nicht um die Höhe: wächst
 * die Zahl der Abfragen mit der Zeilenzahl, fehlt irgendwo ein `with()`.
 */
it('fragt nicht mehr ab, nur weil mehr Zeilen da sind', function (string $komponente, callable $saat): void {
    $benutzer = User::factory()->create();

    $saat(3);
    $klein = abfragenBeimAufbau($komponente, $benutzer);

    $saat(12);
    $gross = abfragenBeimAufbau($komponente, $benutzer);

    expect($gross)->toBe($klein);
})->with('listen');

it('zaehlt die Statusleiste in einer einzigen gruppierten Abfrage', function (): void {
    $typ = ProjectType::factory()->create();
    Project::factory()->count(4)->for($typ)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::actingAs(User::factory()->create())->test(ProjectList::class)->html();
    $abfragen = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $zaehlungen = $abfragen->filter(
        fn (string $abfrage): bool => str_contains($abfrage, 'count(*)') && str_contains($abfrage, 'from `projects`'),
    );

    // Die Leiste hat acht Schaltflächen. Eine Zählung je Status hieße eine
    // Abfrage mehr mit jedem künftigen Status — es darf keine geben.
    expect($zaehlungen->filter(fn (string $abfrage): bool => str_contains($abfrage, 'group by `status`')))
        ->toHaveCount(1)
        ->and($zaehlungen->filter(fn (string $abfrage): bool => str_contains($abfrage, 'where `status` = ?')))
        ->toBeEmpty();
});

it('durchsucht den Katalogabgleich nur einmal je Aufbau', function (): void {
    CustomerService::factory()->count(3)->create();

    $komponente = Livewire::actingAs(User::factory()->create())->test(ServiceOverview::class);

    // Erst ab hier zählen: jeder Livewire-Aufruf baut die Komponente neu auf,
    // und der Zwischenspeicher gilt nur für einen Aufbau.
    DB::flushQueryLog();
    DB::enableQueryLog();
    $komponente->call('toggleCatalogFilter');
    $abfragen = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    // Der Abgleich lädt alle Leistungen mit Katalogherkunft. Hinweis und
    // Filter brauchen dasselbe Ergebnis — er darf nicht zweimal laufen.
    $scans = $abfragen->filter(
        fn (string $abfrage): bool => str_contains($abfrage, 'catalog_snapshot` is not null'),
    );

    expect($scans)->toHaveCount(1);
});
