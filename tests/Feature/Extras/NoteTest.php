<?php

use App\Enums\NoteCategory;
use App\Livewire\Shared\NotesPanel;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('legt Notizen an Kunden, Ansprechpartnern und Kundenleistungen an', function (string $modelClass): void {
    $notable = $modelClass::factory()->create();

    $notable->notes()->create([
        'category' => NoteCategory::Technical,
        'body' => 'Zugangsdaten liegen im Passwortmanager.',
    ]);

    expect($notable->notes)->toHaveCount(1)
        ->and($notable->notes->first()->category)->toBe(NoteCategory::Technical);
})->with([
    'Kunde' => Customer::class,
    'Ansprechpartner' => Contact::class,
    'Kundenleistung' => CustomerService::class,
]);

it('speichert Text, Kategorie, Benutzer und Zeitpunkt', function (): void {
    $user = User::factory()->create(['name' => 'Katrin Berger']);
    $customer = Customer::factory()->create();

    Livewire::actingAs($user)
        ->test(NotesPanel::class, ['notable' => $customer])
        ->call('create')
        ->set('category', NoteCategory::Billing->value)
        ->set('body', 'Rechnungen bitte quartalsweise.')
        ->call('save')
        ->assertHasNoErrors();

    $note = $customer->notes()->firstOrFail();

    expect($note->body)->toBe('Rechnungen bitte quartalsweise.')
        ->and($note->category)->toBe(NoteCategory::Billing)
        ->and($note->user_id)->toBe($user->id)
        ->and($note->created_at)->not->toBeNull();
});

it('verlangt einen Text', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(NotesPanel::class, ['notable' => Customer::factory()->create()])
        ->call('create')
        ->call('save')
        ->assertHasErrors('body');
});

it('bearbeitet und loescht eine Notiz', function (): void {
    $customer = Customer::factory()->create();
    $note = $customer->notes()->create(['category' => NoteCategory::General, 'body' => 'Alter Text']);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(NotesPanel::class, ['notable' => $customer]);

    $component->call('edit', $note->id)
        ->set('body', 'Neuer Text')
        ->call('save')
        ->assertHasNoErrors();

    expect($note->fresh()->body)->toBe('Neuer Text');

    $component->call('delete', $note->id);

    expect(Note::query()->whereKey($note->id)->exists())->toBeFalse();
});

it('filtert Notizen nach Kategorie', function (): void {
    $customer = Customer::factory()->create();

    $customer->notes()->create(['category' => NoteCategory::Technical, 'body' => 'Technischer Hinweis']);
    $customer->notes()->create(['category' => NoteCategory::Billing, 'body' => 'Abrechnungshinweis']);

    Livewire::actingAs(User::factory()->create())
        ->test(NotesPanel::class, ['notable' => $customer])
        ->set('filterCategory', NoteCategory::Technical->value)
        ->assertSee('Technischer Hinweis')
        ->assertDontSee('Abrechnungshinweis');
});

it('bearbeitet keine Notiz eines anderen Datensatzes', function (): void {
    $ersterKunde = Customer::factory()->create();
    $zweiterKunde = Customer::factory()->create();

    $fremdeNotiz = $zweiterKunde->notes()->create(['category' => NoteCategory::General, 'body' => 'Fremd']);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(NotesPanel::class, ['notable' => $ersterKunde]);

    expect(fn () => $component->call('delete', $fremdeNotiz->id))->toThrow(Exception::class);

    expect(Note::query()->whereKey($fremdeNotiz->id)->exists())->toBeTrue();
});
