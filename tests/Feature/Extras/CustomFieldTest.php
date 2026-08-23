<?php

use App\Actions\CustomFields\SaveCustomFieldValues;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Livewire\CustomFields\CustomFieldDefinitionList;
use App\Livewire\CustomFields\CustomFieldsPanel;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomFieldDefinition;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('unterstuetzt alle vorgesehenen Feldtypen', function (CustomFieldType $type): void {
    $definition = CustomFieldDefinition::factory()->ofType(
        $type,
        $type->requiresOptions() ? ['Erste Option', 'Zweite Option'] : null,
    )->create();

    expect($definition->type)->toBe($type)
        ->and($definition->type->validationRules())->not->toBeEmpty();
})->with(CustomFieldType::cases());

it('speichert Werte an Kunden, Artikeln und Kundenleistungen', function (string $modelClass, CustomFieldEntity $entity): void {
    $record = $modelClass::factory()->create();

    $definition = CustomFieldDefinition::factory()->forEntity($entity)->create([
        'key' => 'vertragsnummer',
        'name' => 'Vertragsnummer',
    ]);

    app(SaveCustomFieldValues::class)($record, ['vertragsnummer' => 'V-2026-0815']);

    $record->refresh();

    expect($record->customFieldData())->toBe(['vertragsnummer' => 'V-2026-0815'])
        ->and($record->customFieldValues)->toHaveCount(1)
        ->and($record->customFieldValues->first()->definition->is($definition))->toBeTrue();
})->with([
    'Kunde' => [Customer::class, CustomFieldEntity::Customer],
    'Artikel' => [Product::class, CustomFieldEntity::Product],
    'Kundenleistung' => [CustomerService::class, CustomFieldEntity::CustomerService],
]);

it('speichert Mehrfachauswahlen als Liste', function (): void {
    $customer = Customer::factory()->create();

    CustomFieldDefinition::factory()->ofType(CustomFieldType::MultiSelect, ['A', 'B', 'C'])->create([
        'key' => 'module',
        'name' => 'Module',
    ]);

    app(SaveCustomFieldValues::class)($customer, ['module' => ['A', 'C', '']]);

    expect($customer->fresh()->customFieldData()['module'])->toBe(['A', 'C']);
});

it('speichert Ja-Nein-Felder als Wahrheitswert', function (): void {
    $customer = Customer::factory()->create();

    CustomFieldDefinition::factory()->ofType(CustomFieldType::Boolean)->create([
        'key' => 'notfallkontakt',
        'name' => 'Notfallkontakt',
    ]);

    app(SaveCustomFieldValues::class)($customer, ['notfallkontakt' => '1']);

    expect($customer->fresh()->customFieldData()['notfallkontakt'])->toBeTrue();
});

it('ignoriert unbekannte Feldschluessel', function (): void {
    $customer = Customer::factory()->create();

    app(SaveCustomFieldValues::class)($customer, ['gibtesnicht' => 'Wert']);

    expect($customer->fresh()->customFieldValues)->toHaveCount(0);
});

it('zeigt nur Felder des passenden Bereichs', function (): void {
    CustomFieldDefinition::factory()->forEntity(CustomFieldEntity::Customer)->create(['name' => 'Kundenfeld', 'key' => 'kundenfeld']);
    CustomFieldDefinition::factory()->forEntity(CustomFieldEntity::Product)->create(['name' => 'Artikelfeld', 'key' => 'artikelfeld']);

    $definitionen = Customer::factory()->create()->customFieldDefinitions();

    expect($definitionen->pluck('name')->all())->toBe(['Kundenfeld']);
});

it('blendet inaktive Felder aus', function (): void {
    CustomFieldDefinition::factory()->create(['name' => 'Aktives Feld', 'key' => 'aktiv']);
    CustomFieldDefinition::factory()->create(['name' => 'Inaktives Feld', 'key' => 'inaktiv', 'is_active' => false]);

    expect(Customer::factory()->create()->customFieldDefinitions()->pluck('name')->all())
        ->toBe(['Aktives Feld']);
});

it('erzwingt Pflichtfelder in der Oberflaeche', function (): void {
    $customer = Customer::factory()->create();

    CustomFieldDefinition::factory()->required()->create(['key' => 'vertragsnummer', 'name' => 'Vertragsnummer']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldsPanel::class, ['record' => $customer])
        ->set('values.vertragsnummer', '')
        ->call('save')
        ->assertHasErrors('values.vertragsnummer');
});

it('prueft den Typ eines Wertes', function (): void {
    $customer = Customer::factory()->create();

    CustomFieldDefinition::factory()->ofType(CustomFieldType::Email)->create([
        'key' => 'rechnungsmail',
        'name' => 'Rechnungs-E-Mail',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldsPanel::class, ['record' => $customer])
        ->set('values.rechnungsmail', 'keine-adresse')
        ->call('save')
        ->assertHasErrors('values.rechnungsmail');
});

it('uebernimmt den Standardwert in das Formular', function (): void {
    CustomFieldDefinition::factory()->create([
        'key' => 'betreuungsstufe',
        'name' => 'Betreuungsstufe',
        'default_value' => 'Standard',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldsPanel::class, ['record' => Customer::factory()->create()])
        ->assertSet('values.betreuungsstufe', 'Standard');
});

it('legt eine Felddefinition ueber die Oberflaeche an', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldDefinitionList::class)
        ->call('create')
        ->set('name', 'Vertragsnummer')
        ->assertSet('key', 'vertragsnummer')
        ->set('type', CustomFieldType::Text->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomFieldDefinition::query()->where('key', 'vertragsnummer')->exists())->toBeTrue();
});

it('verlangt Optionen fuer Auswahlfelder', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldDefinitionList::class)
        ->call('create')
        ->set('name', 'Betreuungsstufe')
        ->set('type', CustomFieldType::Select->value)
        ->call('save')
        ->assertHasErrors('optionsInput');
});

it('zerlegt Optionen zeilenweise', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldDefinitionList::class)
        ->call('create')
        ->set('name', 'Betreuungsstufe')
        ->set('type', CustomFieldType::Select->value)
        ->set('optionsInput', "Standard\nErweitert\n\nPremium\nStandard")
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomFieldDefinition::query()->where('key', 'betreuungsstufe')->firstOrFail()->options)
        ->toBe(['Standard', 'Erweitert', 'Premium']);
});

it('lehnt einen ungueltigen Schluessel ab', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldDefinitionList::class)
        ->call('create')
        ->set('name', 'Feld')
        ->set('key', 'Ungültiger Schlüssel')
        ->call('save')
        ->assertHasErrors('key');
});

it('lehnt einen doppelten Schluessel je Bereich ab', function (): void {
    CustomFieldDefinition::factory()->forEntity(CustomFieldEntity::Customer)->create(['key' => 'vertragsnummer']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldDefinitionList::class)
        ->call('create')
        ->set('entity_type', CustomFieldEntity::Customer->value)
        ->set('name', 'Vertragsnummer')
        ->set('key', 'vertragsnummer')
        ->call('save')
        ->assertHasErrors('key');
});

it('erlaubt denselben Schluessel in verschiedenen Bereichen', function (): void {
    CustomFieldDefinition::factory()->forEntity(CustomFieldEntity::Customer)->create(['key' => 'vertragsnummer']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomFieldDefinitionList::class)
        ->call('create')
        ->set('entity_type', CustomFieldEntity::CustomerService->value)
        ->set('name', 'Vertragsnummer')
        ->set('key', 'vertragsnummer')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomFieldDefinition::query()->where('key', 'vertragsnummer')->count())->toBe(2);
});
