<?php

use App\Actions\Pricing\ApplyDuePriceChanges;
use App\Actions\Pricing\CancelPriceChange;
use App\Actions\Pricing\SchedulePriceChange;
use App\Actions\Services\ArchiveCustomerService;
use App\Actions\Services\CreateCustomerService;
use App\Actions\Services\UpdateCustomerService;
use App\Enums\BillingIntervalUnit;
use App\Enums\PriceType;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

it('legt beim Anlegen einer Leistung den Startpunkt des Preisverlaufs an', function (): void {
    $service = app(CreateCustomerService::class)(Customer::factory()->create(), [
        'name' => 'Managed Hosting',
        'purchase_price' => '18,00',
        'sales_price' => '59,00',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
        'billing_interval_count' => 1,
    ]);

    $verlauf = $service->priceChanges()->get();

    expect($verlauf)->toHaveCount(2)
        ->and($verlauf->every(fn (PriceChange $change): bool => $change->isApplied()))->toBeTrue()
        ->and($verlauf->firstWhere('price_type', PriceType::Sales)->new_price_cents)->toBe(5900)
        ->and($verlauf->firstWhere('price_type', PriceType::Sales)->old_price_cents)->toBeNull();
});

it('setzt eine fuer heute geplante Preisaenderung sofort um', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    $change = app(SchedulePriceChange::class)(
        service: $service,
        type: PriceType::Sales,
        newPrice: Money::fromEuroInput('59,00'),
        effectiveDate: now(),
    );

    expect($change->isApplied())->toBeTrue()
        ->and($change->old_price_cents)->toBe(4900)
        ->and($change->new_price_cents)->toBe(5900)
        ->and($service->fresh()->sales_price_cents)->toBe(5900);
});

it('haelt eine zukuenftige Preisaenderung zurueck, bis sie faellig ist', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    $change = app(SchedulePriceChange::class)(
        service: $service,
        type: PriceType::Sales,
        newPrice: Money::fromEuroInput('59,00'),
        effectiveDate: now()->addMonth(),
    );

    expect($change->isScheduled())->toBeTrue()
        // Der aktuelle Preis bleibt bis zum Wirksamkeitsdatum unveraendert.
        ->and($service->fresh()->sales_price_cents)->toBe(4900);
});

it('lehnt rueckwirkende Preisaenderungen ab', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    expect(fn () => app(SchedulePriceChange::class)(
        service: $service,
        type: PriceType::Sales,
        newPrice: Money::fromEuroInput('59,00'),
        effectiveDate: now()->subDay(),
    ))->toThrow(ValidationException::class);

    expect($service->fresh()->sales_price_cents)->toBe(4900)
        ->and(PriceChange::query()->count())->toBe(0);
});

it('erlaubt mehrere zukuenftige Preisaenderungen nebeneinander', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addMonth());
    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('69,00'), now()->addMonths(6));
    app(SchedulePriceChange::class)($service, PriceType::Purchase, Money::fromEuroInput('22,00'), now()->addMonths(3));

    expect($service->priceChanges()->scheduled()->count())->toBe(3)
        ->and($service->fresh()->sales_price_cents)->toBe(4900);
});

it('setzt faellige Preisaenderungen zum Wirksamkeitsdatum um', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addDays(10));

    // Vor dem Wirksamkeitsdatum passiert nichts.
    expect(app(ApplyDuePriceChanges::class)())->toBe(0)
        ->and($service->fresh()->sales_price_cents)->toBe(4900);

    $this->travelTo(now()->addDays(10));

    expect(app(ApplyDuePriceChanges::class)())->toBe(1)
        ->and($service->fresh()->sales_price_cents)->toBe(5900)
        ->and($service->priceChanges()->scheduled()->count())->toBe(0);
});

it('setzt mehrere faellige Aenderungen in der richtigen Reihenfolge um', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addMonth());
    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('69,00'), now()->addMonths(2));

    $this->travelTo(now()->addMonths(3));

    expect(app(ApplyDuePriceChanges::class)())->toBe(2)
        ->and($service->fresh()->sales_price_cents)->toBe(6900);

    $verlauf = $service->priceChanges()->orderBy('effective_date')->get();

    expect($verlauf[0]->old_price_cents)->toBe(4900)
        ->and($verlauf[0]->new_price_cents)->toBe(5900)
        ->and($verlauf[1]->old_price_cents)->toBe(5900)
        ->and($verlauf[1]->new_price_cents)->toBe(6900);
});

it('liefert nach Anwendung den korrekten aktuellen Preis', function (): void {
    $service = CustomerService::factory()->create([
        'purchase_price_cents' => 1800,
        'sales_price_cents' => 4900,
    ]);

    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now());
    app(SchedulePriceChange::class)($service, PriceType::Purchase, Money::fromEuroInput('22,00'), now());

    $service->refresh();

    expect($service->salesPrice()->format())->toBe('59,00 €')
        ->and($service->purchasePrice()->format())->toBe('22,00 €')
        ->and($service->margin()->cents)->toBe(3700);
});

it('haelt Benutzer und Zeitpunkt einer Preisaenderung fest', function (): void {
    $user = User::factory()->create(['name' => 'Sabine Wagner']);
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    $change = app(SchedulePriceChange::class)(
        service: $service,
        type: PriceType::Sales,
        newPrice: Money::fromEuroInput('59,00'),
        effectiveDate: now(),
        user: $user,
        note: 'Preisanpassung 2026',
    );

    expect($change->user_id)->toBe($user->id)
        ->and($change->user->name)->toBe('Sabine Wagner')
        ->and($change->note)->toBe('Preisanpassung 2026')
        ->and($change->applied_at)->not->toBeNull()
        ->and($change->created_at)->not->toBeNull();
});

it('berechnet die Differenz einer Preisaenderung', function (): void {
    $erhoehung = PriceChange::factory()->create(['old_price_cents' => 4900, 'new_price_cents' => 5900]);
    $senkung = PriceChange::factory()->create(['old_price_cents' => 5900, 'new_price_cents' => 4900]);
    $anlage = PriceChange::factory()->create(['old_price_cents' => null, 'new_price_cents' => 4900]);

    expect($erhoehung->difference()->cents)->toBe(1000)
        ->and($senkung->difference()->cents)->toBe(-1000)
        ->and($senkung->difference()->isNegative())->toBeTrue()
        ->and($anlage->difference())->toBeNull();
});

it('loescht eine geplante Preisaenderung', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    $change = app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addMonth());

    app(CancelPriceChange::class)($change);

    expect(PriceChange::query()->whereKey($change->id)->exists())->toBeFalse();
});

it('loescht keine bereits wirksame Preisaenderung', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    $change = app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now());

    expect(fn () => app(CancelPriceChange::class)($change))->toThrow(ValidationException::class);

    expect(PriceChange::query()->whereKey($change->id)->exists())->toBeTrue();
});

it('aendert Preise archivierter Leistungen nicht', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    app(ArchiveCustomerService::class)($service);

    expect(fn () => app(SchedulePriceChange::class)(
        $service, PriceType::Sales, Money::fromEuroInput('59,00'), now(),
    ))->toThrow(ReadOnlyRecordException::class);
});

it('laesst geplante Aenderungen archivierter Leistungen verfallen', function (): void {
    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);

    app(SchedulePriceChange::class)($service, PriceType::Sales, Money::fromEuroInput('59,00'), now()->addMonth());

    app(ArchiveCustomerService::class)($service);

    $this->travelTo(now()->addMonths(2));

    app(ApplyDuePriceChanges::class)();

    expect($service->fresh()->sales_price_cents)->toBe(4900)
        ->and($service->priceChanges()->scheduled()->count())->toBe(0)
        ->and($service->priceChanges()->first()->note)->toContain('archiviert');
});

it('schreibt Preisaenderungen aus dem Bearbeitungsformular in den Verlauf', function (): void {
    $service = app(CreateCustomerService::class)(Customer::factory()->create(), [
        'name' => 'Managed Hosting',
        'purchase_price' => '18,00',
        'sales_price' => '59,00',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
        'billing_interval_count' => 1,
    ]);

    app(UpdateCustomerService::class)($service, [
        'name' => 'Managed Hosting',
        'purchase_price' => '18,00',
        'sales_price' => '49,00',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
        'billing_interval_count' => 1,
    ]);

    $verkaufsverlauf = $service->priceChanges()
        ->where('price_type', PriceType::Sales)
        ->orderBy('id')
        ->get();

    expect($service->fresh()->sales_price_cents)->toBe(4900)
        ->and($verkaufsverlauf)->toHaveCount(2)
        ->and($verkaufsverlauf->last()->old_price_cents)->toBe(5900)
        ->and($verkaufsverlauf->last()->new_price_cents)->toBe(4900)
        // Der Einkaufspreis blieb unveraendert und erzeugt keinen Eintrag.
        ->and($service->priceChanges()->where('price_type', PriceType::Purchase)->count())->toBe(1);
});
