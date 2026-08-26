<?php

use App\Livewire\Registrar\CertificateList;
use App\Livewire\Registrar\DomainList;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('zeigt den Domainbestand mit Ablauf und Zuordnung', function (): void {
    $kunde = Customer::factory()->create(['company_name' => 'Müller Elektro GmbH']);

    Domain::factory()->create(['name' => 'zugeordnet.de', 'customer_id' => $kunde->id]);
    Domain::factory()->create(['name' => 'ohne-kunde.de']);

    Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->assertSee('zugeordnet.de')
        ->assertSee('ohne-kunde.de')
        ->assertSee('Ohne Kunde');
});

it('filtert auf Domains ohne Kunde', function (): void {
    $kunde = Customer::factory()->create();

    Domain::factory()->create(['name' => 'zugeordnet.de', 'customer_id' => $kunde->id]);
    Domain::factory()->create(['name' => 'ohne-kunde.de']);

    Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->set('assignment', 'unassigned')
        ->assertSee('ohne-kunde.de')
        ->assertDontSee('zugeordnet.de');
});

it('filtert auf bald ablaufende und abgelaufene Domains', function (): void {
    Domain::factory()->expiringSoon()->create(['name' => 'bald.de']);
    Domain::factory()->expired()->create(['name' => 'abgelaufen.de']);
    Domain::factory()->create(['name' => 'ruhig.de', 'expires_on' => now()->addYear()]);

    $komponente = Livewire::actingAs($this->benutzer)->test(DomainList::class);

    $komponente->set('expiry', 'soon')
        ->assertSee('bald.de')
        ->assertDontSee('abgelaufen.de')
        ->assertDontSee('ruhig.de');

    $komponente->set('expiry', 'expired')
        ->assertSee('abgelaufen.de')
        ->assertDontSee('bald.de');
});

it('zaehlt den Bestand in den Kennzahlen', function (): void {
    $kunde = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $kunde->id]);
    Domain::factory()->count(2)->create();
    Domain::factory()->expiringSoon()->create();
    Domain::factory()->expired()->create();

    $kennzahlen = Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->instance()
        ->metrics();

    expect($kennzahlen['total'])->toBe(5)
        ->and($kennzahlen['unassigned'])->toBe(4)
        ->and($kennzahlen['expiringSoon'])->toBe(1)
        ->and($kennzahlen['expired'])->toBe(1);
});

it('sortiert Domains ohne Ablaufdatum ans Ende', function (): void {
    Domain::factory()->create(['name' => 'ohne-datum.de', 'expires_on' => null]);
    Domain::factory()->create(['name' => 'mit-datum.de', 'expires_on' => now()->addMonths(6)]);

    $namen = Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->viewData('domains')
        ->pluck('name')
        ->all();

    // Ein fehlendes Datum ist kein früher Termin, sondern eine Lücke.
    expect($namen)->toBe(['mit-datum.de', 'ohne-datum.de']);
});

it('zeigt den Zertifikatsbestand', function (): void {
    Certificate::factory()->create(['common_name' => 'www.beispiel.de', 'issuer' => 'Sectigo']);

    Livewire::actingAs($this->benutzer)
        ->test(CertificateList::class)
        ->assertSee('www.beispiel.de')
        ->assertSee('Sectigo');
});

it('nennt in der Navigation die Zahl der Domains und Zertifikate', function (): void {
    Domain::factory()->count(3)->create();
    Certificate::factory()->count(2)->create();

    $this->actingAs($this->benutzer)
        ->get(route('domains.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'Domains</span>',
            '<span class="font-mono text-[10px] tabular-nums text-ink-faint">3</span>',
            'Zertifikate</span>',
            '<span class="font-mono text-[10px] tabular-nums text-ink-faint">2</span>',
        ], escape: false);
});
