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

it('haelt die Formularseite der Anmeldung hell', function (): void {
    // Ohne die Klasse `dark` am <html> loesen alle Tokens auf ihre hellen
    // Werte auf — einschliesslich der TallStackUI-Formularfelder.
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="de" class="h-full">', escape: false)
        ->assertDontSee('tallstackui_darkTheme', escape: false);
});

it('bietet auf der Anmeldeseite keine Farbschema-Auswahl an', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('theme-switch', escape: false);
});

it('zeigt das Raster hinter der Markenspalte', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('id="marken-raster"', escape: false)
        ->assertSee('aria-hidden="true"', escape: false)
        ->assertSee('pointer-events-none', escape: false);
});

it('stellt die Markenspalte auf die vom Farbschema unabhaengigen Tokens', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('bg-brand-shell', escape: false)
        ->assertSee('text-brand-text', escape: false);
});

it('gibt den Eingabefeldern eine waagerechte Polsterung', function (): void {
    // TallStackUI liefert fuer das Basisfeld nur `py-1.5`; ohne die
    // Anpassung in AppServiceProvider klebt der Text am linken Rand.
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('px-3', escape: false);
});

it('zeigt die angemeldete Oberflaeche dauerhaft dunkel', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<html lang="de" class="dark h-full">', escape: false)
        ->assertDontSee('theme-switch', escape: false);
});
