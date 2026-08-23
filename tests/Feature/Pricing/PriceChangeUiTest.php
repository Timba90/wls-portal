<?php

use App\Actions\Pricing\SchedulePriceChange;
use App\Enums\PriceType;
use App\Livewire\Services\CustomerServiceDetail;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('plant eine Preisaenderung ueber die Detailseite', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create(['sales_price_cents' => 4900]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->call('openPriceChangeForm', PriceType::Sales->value)
        ->assertSet('priceChangeValue', '49,00')
        ->set('priceChangeValue', '59,00')
        ->set('priceChangeEffectiveDate', now()->addMonth()->toDateString())
        ->set('priceChangeNote', 'Preisanpassung 2026')
        ->call('savePriceChange')
        ->assertHasNoErrors()
        ->assertSet('showPriceChangeForm', false);

    $change = $service->priceChanges()->scheduled()->firstOrFail();

    expect($change->new_price_cents)->toBe(5900)
        ->and($change->note)->toBe('Preisanpassung 2026')
        ->and($service->fresh()->sales_price_cents)->toBe(4900);
});

it('lehnt ein rueckwirkendes Wirksamkeitsdatum im Formular ab', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->call('openPriceChangeForm', PriceType::Sales->value)
        ->set('priceChangeValue', '59,00')
        ->set('priceChangeEffectiveDate', now()->subDay()->toDateString())
        ->call('savePriceChange')
        ->assertHasErrors('priceChangeEffectiveDate');

    expect(PriceChange::query()->scheduled()->count())->toBe(0);
});

it('lehnt einen ungueltigen Geldbetrag im Formular ab', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->call('openPriceChangeForm', PriceType::Sales->value)
        ->set('priceChangeValue', 'kein Betrag')
        ->set('priceChangeEffectiveDate', now()->toDateString())
        ->call('savePriceChange')
        ->assertHasErrors('priceChangeValue');
});

it('zeigt geplante und wirksame Preisaenderungen an', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create(['sales_price_cents' => 4900]);

    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('54,00'), now());
    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addMonth());

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->assertSee('Geplante Änderungen')
        ->assertSee('59,00 €')
        ->assertSee('Wirksam gewordene Änderungen')
        ->assertSee('54,00 €');
});

it('loescht eine geplante Preisaenderung ueber die Detailseite', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    $change = app(SchedulePriceChange::class)(
        $service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addMonth(),
    );

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->call('cancelPriceChange', $change->id);

    expect(PriceChange::query()->whereKey($change->id)->exists())->toBeFalse();
});

it('loescht keine Preisaenderung einer anderen Leistung', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    $fremdeAenderung = PriceChange::factory()->create();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service]);

    expect(fn () => $component->call('cancelPriceChange', $fremdeAenderung->id))
        ->toThrow(ModelNotFoundException::class);

    expect(PriceChange::query()->whereKey($fremdeAenderung->id)->exists())->toBeTrue();
});

it('fuehrt faellige Preisaenderungen ueber den Konsolenbefehl aus', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addDay());

    $this->travelTo(now()->addDays(2));

    $this->artisan('preise:faellige-anwenden')
        ->expectsOutputToContain('1 Preisänderung wurde wirksam.')
        ->assertSuccessful();

    expect($service->fresh()->sales_price_cents)->toBe(5900);
});
