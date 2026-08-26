<?php

use App\Livewire\Catalog\ProductList;
use App\Livewire\Contacts\ContactList;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Registrar\CertificateList;
use App\Livewire\Registrar\DomainList;
use App\Livewire\Services\ServiceOverview;
use App\Models\User;
use Livewire\Livewire;

/**
 * Sortierspalte und -richtung stehen in oeffentlichen Eigenschaften und
 * kommen damit aus der URL — der Browser kann beides frei setzen. Ungeprueft
 * weitergereicht wirft Laravel bei einer fremden Richtung eine Ausnahme, und
 * die Seite antwortet mit 500 statt mit einer Liste.
 */
beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

dataset('listen', [
    'Kunden' => [CustomerList::class],
    'Ansprechpartner' => [ContactList::class],
    'Katalog' => [ProductList::class],
    'Leistungen' => [ServiceOverview::class],
    'Projekte' => [ProjectList::class],
    'Domains' => [DomainList::class],
    'Zertifikate' => [CertificateList::class],
]);

it('haelt einer erfundenen Sortierrichtung stand', function (string $komponente): void {
    Livewire::actingAs($this->benutzer)
        ->test($komponente)
        ->set('sort', ['column' => 'name', 'direction' => 'seitwärts'])
        ->assertOk();
})->with('listen');

it('haelt einer erfundenen Sortierspalte stand', function (string $komponente): void {
    Livewire::actingAs($this->benutzer)
        ->test($komponente)
        ->set('sort', ['column' => 'name); drop table users; --', 'direction' => 'asc'])
        ->assertOk();
})->with('listen');
