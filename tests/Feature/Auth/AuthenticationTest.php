<?php

use App\Models\User;

it('zeigt die Anmeldeseite an', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Anmelden')
        ->assertSee('Passwort vergessen?');
});

it('meldet einen Benutzer mit korrekten Zugangsdaten an', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'GeheimesPasswort1!',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('lehnt falsche Zugangsdaten ab', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'falschesPasswort1!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('meldet einen Benutzer wieder ab', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

it('bietet keine oeffentliche Registrierung an', function (): void {
    expect(app('router')->getRoutes()->getByName('register'))->toBeNull();

    $this->post('/register', [
        'name' => 'Neuer Benutzer',
        'email' => 'neu@example.test',
        'password' => 'GeheimesPasswort1!',
        'password_confirmation' => 'GeheimesPasswort1!',
    ])->assertNotFound();
});

it('leitet nicht angemeldete Besucher zur Anmeldung', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->get(route('profile.show'))->assertRedirect(route('login'));
});

it('laesst angemeldete Benutzer auf das Dashboard', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');
});
