<?php

use App\Livewire\Profile\SecurityPage;
use App\Models\User;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('ist standardmaessig nicht aktiv', function (): void {
    expect(User::factory()->create()->hasTwoFactorEnabled())->toBeFalse();
});

it('erzeugt beim Aktivieren ein Geheimnis und Wiederherstellungscodes', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SecurityPage::class)
        ->call('enableTwoFactor')
        ->assertSet('showingQrCode', true);

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->recoveryCodes())->toHaveCount(8);
});

it('bestaetigt die Aktivierung mit einem gueltigen Code', function (): void {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(SecurityPage::class)
        ->call('enableTwoFactor');

    $user->refresh();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    $component->set('code', $code)
        ->call('confirmTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('lehnt einen falschen Bestaetigungscode ab', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SecurityPage::class)
        ->call('enableTwoFactor')
        ->set('code', '000000')
        ->call('confirmTwoFactor')
        ->assertHasErrors('code');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('deaktiviert die Zwei-Faktor-Authentifizierung wieder', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test(SecurityPage::class)
        ->call('disableTwoFactor');

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();
});

it('erzeugt neue Wiederherstellungscodes', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $alteCodes = $user->recoveryCodes();

    Livewire::actingAs($user)
        ->test(SecurityPage::class)
        ->call('regenerateRecoveryCodes');

    expect($user->fresh()->recoveryCodes())
        ->toHaveCount(8)
        ->not->toEqual($alteCodes);
});

it('verlangt nach der Anmeldung eine Zwei-Faktor-Bestaetigung', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'GeheimesPasswort1!',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('erzwingt 2FA global, wenn es konfiguriert ist', function (): void {
    config()->set('auth.two_factor_required', true);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('profile.security'));
});

it('laesst Benutzer mit aktivem 2FA passieren, wenn es erzwungen wird', function (): void {
    config()->set('auth.two_factor_required', true);

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('dashboard'))
        ->assertOk();
});
