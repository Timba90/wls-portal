<?php

use App\Livewire\Users\UserList;
use App\Models\User;
use Livewire\Livewire;

it('legt einen neuen Benutzer an', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(UserList::class)
        ->call('create')
        ->set('name', 'Sabine Wagner')
        ->set('email', 'sabine.wagner@example.test')
        ->set('password', 'SicheresPasswort1!')
        ->set('password_confirmation', 'SicheresPasswort1!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(User::query()->where('email', 'sabine.wagner@example.test')->exists())->toBeTrue();
});

it('verlangt beim Anlegen ein regelkonformes Passwort', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(UserList::class)
        ->call('create')
        ->set('name', 'Sabine Wagner')
        ->set('email', 'sabine.wagner@example.test')
        ->set('password', 'kurz')
        ->set('password_confirmation', 'kurz')
        ->call('save')
        ->assertHasErrors('password');
});

it('lehnt eine bereits vergebene E-Mail-Adresse ab', function (): void {
    $bestehend = User::factory()->create(['email' => 'vorhanden@example.test']);

    Livewire::actingAs($bestehend)
        ->test(UserList::class)
        ->call('create')
        ->set('name', 'Zweiter Benutzer')
        ->set('email', 'vorhanden@example.test')
        ->set('password', 'SicheresPasswort1!')
        ->set('password_confirmation', 'SicheresPasswort1!')
        ->call('save')
        ->assertHasErrors('email');
});

it('bearbeitet einen Benutzer ohne das Passwort zu aendern', function (): void {
    $user = User::factory()->create(['name' => 'Alter Name']);
    $passwort = $user->password;

    Livewire::actingAs($user)
        ->test(UserList::class)
        ->call('edit', $user->id)
        ->set('name', 'Neuer Name')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Neuer Name')
        ->and($user->password)->toBe($passwort);
});

it('durchsucht Benutzer nach Name und E-Mail-Adresse', function (): void {
    User::factory()->create(['name' => 'Katrin Berger', 'email' => 'k.berger@example.test']);
    User::factory()->create(['name' => 'Thomas Lindner', 'email' => 't.lindner@example.test']);

    Livewire::actingAs(User::factory()->create(['name' => 'Admin Benutzer']))
        ->test(UserList::class)
        ->set('search', 'Berger')
        ->assertSee('Katrin Berger')
        ->assertDontSee('Thomas Lindner');
});
