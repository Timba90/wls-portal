<?php

use App\Actions\Customers\GenerateCustomerNumber;
use App\Exceptions\ImmutableAttributeException;
use App\Models\Customer;
use App\Models\Sequence;
use App\Support\Numbering\SequenceGenerator;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('vergibt Kundennummern fortlaufend im Format KD-00001', function (): void {
    $generate = app(GenerateCustomerNumber::class);

    expect($generate())->toBe('KD-00001')
        ->and($generate())->toBe('KD-00002')
        ->and($generate())->toBe('KD-00003');
});

it('vergibt beim Anlegen eines Kunden automatisch eine Kundennummer', function (): void {
    $ersterKunde = Customer::factory()->create();
    $zweiterKunde = Customer::factory()->create();

    expect($ersterKunde->customer_number)->toBe('KD-00001')
        ->and($zweiterKunde->customer_number)->toBe('KD-00002');
});

it('fuellt die Nummer bei hohen Zaehlerstaenden korrekt auf', function (): void {
    Sequence::query()->create(['key' => GenerateCustomerNumber::SEQUENCE_KEY, 'next_value' => 99999]);

    $generate = app(GenerateCustomerNumber::class);

    expect($generate())->toBe('KD-99999')
        ->and($generate())->toBe('KD-100000');
});

it('haelt die Kundennummer aus der Massenzuweisung heraus', function (): void {
    $customer = Customer::factory()->create();

    expect(fn () => $customer->update(['customer_number' => 'KD-99999']))
        ->toThrow(MassAssignmentException::class);

    expect($customer->fresh()->customer_number)->toBe('KD-00001');
});

it('laesst die Kundennummer auch bei direkter Zuweisung nicht mehr aendern', function (): void {
    $customer = Customer::factory()->create();

    $customer->customer_number = 'KD-99999';

    expect(fn () => $customer->save())->toThrow(ImmutableAttributeException::class);

    expect($customer->fresh()->customer_number)->toBe('KD-00001');
});

it('verwendet die Nummer eines archivierten Kunden nicht erneut', function (): void {
    $customer = Customer::factory()->create();

    expect($customer->customer_number)->toBe('KD-00001');

    // Selbst eine harte Loeschung darf die Nummer nicht freigeben.
    $customer->delete();

    expect(Customer::factory()->create()->customer_number)->toBe('KD-00002');
});

it('vergibt auch bei parallelen Aufrufen keine Nummer doppelt', function (): void {
    $generator = app(SequenceGenerator::class);

    $nummern = collect(range(1, 50))
        ->map(fn (): int => $generator->next('test_sequence'))
        ->all();

    expect($nummern)->toBe(range(1, 50))
        ->and(array_unique($nummern))->toHaveCount(50);
});

it('legt eine Sequenz beim ersten Zugriff an', function (): void {
    expect(Sequence::query()->where('key', 'neue_sequenz')->exists())->toBeFalse();

    app(SequenceGenerator::class)->next('neue_sequenz');

    expect(Sequence::query()->where('key', 'neue_sequenz')->value('next_value'))->toBe(2);
});
