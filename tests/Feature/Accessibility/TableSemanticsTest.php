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
use App\Models\User;
use Livewire\Livewire;

/**
 * Die Listen sind Raster aus <div>, nicht aus <table> — nur so lassen sich die
 * Spaltenanteile des Entwurfs sauber setzen. Ohne Rollen wäre die Struktur für
 * Screenreader aber nicht vorhanden: keine Spaltenzuordnung, keine
 * Zeilennavigation, nur eine Wand aus Text.
 */
dataset('rastertabellen', [
    'Kundenliste' => [CustomerList::class, fn () => Customer::factory()->create(), 'Kunden'],
    'Artikelkatalog' => [ProductList::class, fn () => Product::factory()->create(), 'Artikel'],
    'Projektliste' => [ProjectList::class, fn () => Project::factory()->create(), 'Projekte'],
    'Leistungsübersicht' => [ServiceOverview::class, fn () => CustomerService::factory()->create(), 'Kundenleistungen'],
    'Ansprechpartnerliste' => [ContactList::class, function (): Contact {
        $kunde = Customer::factory()->company()->create();
        $kontakt = Contact::factory()->create();
        $kontakt->assignments()->create(['customer_id' => $kunde->id]);

        return $kontakt;
    }, 'Ansprechpartner'],
]);

it('gibt der Rastertabelle eine Struktur für Screenreader', function (string $komponente, callable $saat, string $beschriftung): void {
    $saat();

    $html = Livewire::actingAs(User::factory()->create())->test($komponente)->html();

    expect($html)->toContain('role="table"')
        ->and($html)->toContain('aria-label="'.$beschriftung.'"')
        ->and($html)->toContain('role="row"')
        ->and($html)->toContain('role="columnheader"')
        ->and($html)->toContain('role="cell"');
})->with('rastertabellen');

it('behält die ganze Zeile als Link', function (string $komponente, callable $saat): void {
    $saat();

    $html = Livewire::actingAs(User::factory()->create())->test($komponente)->html();

    // Der Link liegt in der ersten Zelle und spannt sich per `after` über die
    // Zeile. Ein <a> mit `role="row"` verlöre seine Linkrolle, ein
    // Klick-Handler statt eines Links verlöre Mittelklick und neuen Tab.
    expect($html)->toContain('after:absolute after:inset-0');
})->with('rastertabellen');
