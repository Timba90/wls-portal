<?php

use App\Models\Certificate;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;

it('resets filters in every overview and keeps subsequent searches usable', function (string $path, string $model, array $attributes, string $marker, array $filters): void {
    $this->actingAs(User::factory()->create());
    $model::factory()->create($attributes);
    $query = http_build_query(['suche' => 'ohne-treffer', ...$filters]);

    visit($path.'?'.$query)
        ->click('Filter zurücksetzen')
        ->waitForText($marker)
        ->assertQueryStringMissing('suche')
        ->fill('[wire\\:model\\.live\\.debounce\\.300ms="search"]', 'erneut-ohne-treffer')
        ->assertDontSee($marker)
        ->click('Filter zurücksetzen')
        ->waitForText($marker)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
})->with([
    'customers' => ['/kunden', Customer::class, ['company_name' => 'Filterprobe GmbH', 'short_label' => 'Filterprobe'], 'Filterprobe GmbH', ['typ' => 'company', 'verantwortlich' => '99999']],
    'projects' => ['/projekte', Project::class, ['name' => 'Filterprobe Projekt'], 'Filterprobe Projekt', ['typ' => '99999', 'verantwortlich' => '99999']],
    'services' => ['/leistungen', CustomerService::class, ['name' => 'Filterprobe Leistung'], 'Filterprobe Leistung', ['artikel' => '99999', 'kategorie' => '99999', 'verantwortlich' => '99999', 'abrechnung' => 'do_not_bill']],
    'products' => ['/artikel', Product::class, ['name' => 'Filterprobe Artikel'], 'Filterprobe Artikel', ['kategorie' => '99999', 'tag' => '99999']],
    'contacts' => ['/ansprechpartner', Contact::class, ['first_name' => 'Filterprobe', 'last_name' => 'Kontakt'], 'Kontakt, Filterprobe', ['rolle' => '99999', 'status' => 'archived']],
    'domains' => ['/domains', Domain::class, ['name' => 'filterprobe-domain.de'], 'filterprobe-domain.de', ['anbieter' => 'manual', 'zuordnung' => 'assigned', 'kunde' => '99999', 'ablauf' => 'expired']],
    'certificates' => ['/zertifikate', Certificate::class, ['common_name' => 'filterprobe-zertifikat.de'], 'filterprobe-zertifikat.de', ['anbieter' => 'autodns', 'zuordnung' => 'assigned', 'ablauf' => 'expired']],
]);

it('clears an individual styled select without a server error', function (): void {
    $this->actingAs(User::factory()->create());
    $type = ProjectType::factory()->create();
    Project::factory()->create(['name' => 'Projekt mit Typ', 'project_type_id' => $type->id]);
    Project::factory()->create(['name' => 'Projekt ohne Typ', 'project_type_id' => null]);

    visit('/projekte?typ='.$type->id)
        ->assertSee('Projekt mit Typ')
        ->assertDontSee('Projekt ohne Typ')
        ->click('[dusk="tallstackui_select_clear"]')
        ->waitForText('Projekt ohne Typ')
        ->assertSee('Projekt mit Typ')
        ->click('Filter zurücksetzen')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
