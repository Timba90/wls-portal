<?php

use App\Livewire\Subscriptions\SubscriptionDetail;
use App\Livewire\Subscriptions\SubscriptionForm;
use App\Livewire\Subscriptions\SubscriptionList;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('requires authentication for every subscription screen', function (): void {
    $subscription = Subscription::factory()->create();
    foreach (['subscriptions.index', 'subscriptions.create', 'subscriptions.show', 'subscriptions.edit'] as $route) {
        $this->get(route($route, in_array($route, ['subscriptions.show', 'subscriptions.edit'], true) ? $subscription : []))->assertRedirect(route('login'));
    }
});

it('shows an empty manually managed inventory', function (): void {
    Livewire::actingAs($this->user)->test(SubscriptionList::class)
        ->assertSee('Keine Subscriptions gefunden.')
        ->assertSee('Subscription anlegen');
});

it('filters subscriptions and accepts cleared nullable selects', function (): void {
    $customer = Customer::factory()->create();
    Subscription::factory()->create(['name' => 'treffer.example', 'server' => 'cloud02', 'status' => 'suspended', 'customer_id' => $customer->id]);
    Subscription::factory()->create(['name' => 'anders.example', 'server' => 'cloud01', 'status' => 'active']);
    Livewire::actingAs($this->user)->test(SubscriptionList::class)
        ->assertSee('treffer.example')->assertSee('anders.example')
        ->call('setPage', 2)->set('server', 'cloud02')->assertSet('paginators.page', 1)->set('status', 'suspended')->set('customerFilter', (string) $customer->id)
        ->set('search', 'treffer')->assertSee('treffer.example')->assertDontSee('anders.example')
        ->set('server', null)->set('status', null)->set('customerFilter', null)->assertHasNoErrors()
        ->call('resetFilters')->assertSet('search', '')->assertSet('server', '')->assertSet('status', '')->assertSet('customerFilter', '')
        ->assertSee('anders.example');
});

it('creates and edits the complete manual webspace metadata', function (): void {
    Livewire::actingAs($this->user)->test(SubscriptionForm::class)
        ->set('name', ' Beispiel.DE. ')->set('server', 'cloud03')->set('status', 'disabled')
        ->set('subscriber', 'Original Plesk Kunde')->set('setup_date', '2020-02-03')->set('plesk_id', '42')->set('hosting_login', 'webspace')->set('service_plan', 'Business')
        ->set('ip_address', '192.0.2.10')->set('expires_on', '2027-10-21')->set('description', 'Manuell erfasst')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('subscriptions.show', Subscription::query()->sole()));
    $subscription = Subscription::query()->sole();
    expect($subscription->name)->toBe('beispiel.de')->and($subscription->service_plan)->toBe('Business')->and($subscription->customer_id)->toBeNull();
    Livewire::actingAs($this->user)->test(SubscriptionForm::class, ['subscription' => $subscription])
        ->assertSet('subscriber', 'Original Plesk Kunde')->assertSet('setup_date', '2020-02-03')->assertSet('server', 'cloud03')->assertSet('plesk_id', '42')->set('service_plan', 'Premium')
        ->call('save')->assertHasNoErrors();
    expect($subscription->fresh()->service_plan)->toBe('Premium');
    Livewire::actingAs($this->user)->test(SubscriptionDetail::class, ['subscription' => $subscription->fresh()])
        ->assertSee('Premium')->assertSee('Verlauf')->assertSee('Keine Leistung zugeordnet');
});

it('clears the service when changing customer and rejects a forged ownership link', function (): void {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $service = CustomerService::factory()->create(['customer_id' => $customer->id]);
    Livewire::actingAs($this->user)->test(SubscriptionForm::class)
        ->set('name', 'ownership.example')->set('server', 'cloud01')->set('customer_id', (string) $customer->id)
        ->set('customer_service_id', (string) $service->id)->set('customer_id', (string) $other->id)
        ->assertSet('customer_service_id', null)->set('customer_service_id', (string) $service->id)
        ->call('save')->assertHasErrors('customer_service_id');
    expect(Subscription::query()->count())->toBe(0);
});
