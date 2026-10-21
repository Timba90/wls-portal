<?php

use App\Models\CustomerService;
use App\Models\Subscription;
use App\Models\User;

it('opens an empty manual subscriptions section and creates then edits a webspace', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/subscriptions')
        ->assertSee('Subscriptions')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'subscriptions-empty')
        ->click('Subscription anlegen')
        ->click('div[x-data^="tallstackui_select("]:has(label:text-is("Server")) [dusk="tallstackui_select_open_close"]')
        ->click('[role="listbox"]:visible li:has-text("cloud01")')
        ->fill('Hauptdomain', 'Manuelle-Subscription.EXAMPLE.')
        ->fill('Plesk-Tarif', 'Webhosting Small')
        ->fill('Plesk-Abonnent', 'Browser Beispiel GmbH')
        ->fill('Hosting-Login', 'webspace_browser')
        ->click('button[type="submit"]')
        ->waitForText('manuelle-subscription.example')
        ->assertSee('manuelle-subscription.example')
        ->assertSee('cloud01')
        ->assertSee('Keine Leistung zugeordnet')
        ->click('Bearbeiten')
        ->fill('Plesk-Tarif', 'Webhosting Medium')
        ->click('Speichern')
        ->waitForText('Webhosting Medium')
        ->assertSee('Webhosting Medium')
        ->assertNoJavaScriptErrors();

    $subscription = Subscription::query()->sole();
    expect($subscription->getRawOriginal('server'))->toBe('cloud01')
        ->and($subscription->subscriber)->toBe('Browser Beispiel GmbH')
        ->and($subscription->hosting_login)->toBe('webspace_browser');
});

it('filters separate servers and resets styled selects without losing billing labels', function (): void {
    $this->actingAs(User::factory()->create());
    $service = CustomerService::factory()->create(['status' => 'active', 'do_not_bill' => true]);
    Subscription::factory()->create(['server' => 'cloud02', 'name' => 'zweiter-server.example', 'customer_id' => $service->customer_id, 'customer_service_id' => $service->id]);
    Subscription::factory()->create(['server' => 'cloud03', 'name' => 'dritter-server.example']);

    visit('/subscriptions?server=cloud02')
        ->assertSee('zweiter-server.example')
        ->assertSee('Nicht abrechnen')
        ->assertDontSee('dritter-server.example')
        ->click('[dusk="tallstackui_select_clear"]')
        ->waitForText('dritter-server.example')
        ->click('Filter zurücksetzen')
        ->assertSee('zweiter-server.example')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'subscriptions-manual-list');
});

it('selects visible subscriptions and requires modal confirmation before deleting', function (): void {
    $this->actingAs(User::factory()->create());
    $remove = Subscription::factory()->create(['server' => 'cloud02', 'name' => 'alt-loeschen.example']);
    $keep = Subscription::factory()->create(['server' => 'cloud03', 'name' => 'behalten.example']);

    visit('/subscriptions?server=cloud02')
        ->click('input[aria-label="Alle auf dieser Seite auswählen"]')
        ->waitForText('1 ausgewählt')
        ->click('Ausgewählte löschen')
        ->waitForText('Diese 1 Portal-Einträge endgültig löschen?')
        ->click('button:has-text("Abbrechen")')
        ->assertSee('alt-loeschen.example')
        ->click('Ausgewählte löschen')
        ->waitForText('Diese 1 Portal-Einträge endgültig löschen?')
        ->click('button:has-text("Endgültig löschen")')
        ->waitForText('1 Subscriptions gelöscht.')
        ->assertDontSee('alt-loeschen.example')
        ->assertSee('0 ausgewählt')
        ->click('Filter zurücksetzen')
        ->waitForText('behalten.example')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'subscriptions-bulk-delete');

    $this->assertModelMissing($remove);
    $this->assertModelExists($keep);
});
