<?php

use App\Actions\CustomerPortal\InvitePortalUser;
use App\Livewire\CustomerPortal\Auth\ForgotPassword;
use App\Livewire\CustomerPortal\Auth\Login;
use App\Livewire\CustomerPortal\Auth\ResetPassword;
use App\Livewire\Customers\CustomerPortalAccess;
use App\Models\Customer;
use App\Models\PortalUser;
use App\Models\User;
use App\Notifications\CustomerPortal\SetPortalPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('leitet Gaeste des Kundenportals zur Kundenanmeldung', function (): void {
    $this->get(route('customer-portal.dashboard'))->assertRedirect(route('customer-portal.login'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('zeigt die Anmeldeseite des Kundenportals', function (): void {
    $this->get(route('customer-portal.login'))
        ->assertOk()
        ->assertSee('Ihr Zugang zu Projekten, Tickets und Rechnungen.');
});

it('meldet einen Kundenzugang an', function (): void {
    $zugang = PortalUser::factory()->create(['email' => 'kunde@example.test']);

    Livewire::test(Login::class)
        ->set('email', 'kunde@example.test')
        ->set('password', 'KundenPasswort1!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('customer-portal.dashboard'));

    expect(auth('customer')->id())->toBe($zugang->id)
        ->and(auth('web')->check())->toBeFalse()
        ->and($zugang->fresh()->last_login_at)->not->toBeNull();
});

it('weist gesperrte Zugaenge und archivierte Kunden ab', function (string $fall): void {
    $zugang = $fall === 'gesperrt'
        ? PortalUser::factory()->inactive()->create(['email' => 'kunde@example.test'])
        : PortalUser::factory()->for(Customer::factory()->archived())->create(['email' => 'kunde@example.test']);

    Livewire::test(Login::class)
        ->set('email', 'kunde@example.test')
        ->set('password', 'KundenPasswort1!')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth('customer')->check())->toBeFalse();
})->with(['gesperrt', 'archiviert']);

it('begrenzt Fehlversuche bei der Anmeldung', function (): void {
    PortalUser::factory()->create(['email' => 'kunde@example.test']);

    $komponente = Livewire::test(Login::class)->set('email', 'kunde@example.test')->set('password', 'falsch');

    foreach (range(1, 5) as $versuch) {
        $komponente->call('login');
    }

    $komponente->set('password', 'KundenPasswort1!')->call('login')->assertHasErrors('email');

    expect(auth('customer')->check())->toBeFalse();
});

it('trennt interne Benutzer und Kundenzugaenge vollstaendig', function (): void {
    $zugang = PortalUser::factory()->create();

    $this->actingAs($zugang, 'customer')->get(route('customers.index'))->assertRedirect(route('login'));
    $this->actingAs($zugang, 'customer')->get(route('tickets.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(), 'web');
    auth('customer')->logout();

    $this->get(route('customer-portal.dashboard'))->assertRedirect(route('customer-portal.login'));
});

it('beendet die Sitzung eines waehrenddessen gesperrten Zugangs', function (): void {
    $zugang = PortalUser::factory()->create();
    $this->actingAs($zugang, 'customer')->get(route('customer-portal.dashboard'))->assertOk();

    $zugang->update(['is_active' => false]);

    $this->get(route('customer-portal.dashboard'))->assertRedirect(route('customer-portal.login'));
    expect(auth('customer')->check())->toBeFalse();
});

it('laedt einen Kundenzugang ein und laesst ihn sein Passwort festlegen', function (): void {
    Notification::fake();
    $kunde = Customer::factory()->create();

    $zugang = app(InvitePortalUser::class)($kunde, ['name' => 'Anna Kunde', 'email' => 'Anna@Example.test']);

    expect($zugang->email)->toBe('anna@example.test')
        ->and($zugang->hasPassword())->toBeFalse()
        ->and($zugang->invited_at)->not->toBeNull();

    $token = null;
    Notification::assertSentTo($zugang, SetPortalPassword::class, function (SetPortalPassword $mail) use (&$token): bool {
        $token = $mail->token;

        return $mail->invitation === true;
    });

    Livewire::withQueryParams(['email' => 'anna@example.test'])
        ->test(ResetPassword::class, ['token' => $token])
        ->set('password', 'NeuesPasswort1!')
        ->set('password_confirmation', 'NeuesPasswort1!')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('customer-portal.dashboard'));

    expect($zugang->fresh()->hasPassword())->toBeTrue()
        ->and(auth('customer')->id())->toBe($zugang->id);
});

it('lehnt doppelte E-Mail-Adressen und archivierte Kunden bei der Einladung ab', function (): void {
    Notification::fake();
    PortalUser::factory()->create(['email' => 'anna@example.test']);

    expect(fn () => app(InvitePortalUser::class)(Customer::factory()->create(), ['name' => 'Anna', 'email' => 'anna@example.test']))
        ->toThrow(ValidationException::class);

    expect(fn () => app(InvitePortalUser::class)(Customer::factory()->archived()->create(), ['name' => 'Bea', 'email' => 'bea@example.test']))
        ->toThrow(ValidationException::class);
});

it('verraet bei „Passwort vergessen" nicht, ob ein Zugang existiert', function (): void {
    Notification::fake();
    $zugang = PortalUser::factory()->create(['email' => 'kunde@example.test']);
    $gesperrt = PortalUser::factory()->inactive()->create(['email' => 'gesperrt@example.test']);

    foreach (['kunde@example.test', 'unbekannt@example.test', 'gesperrt@example.test'] as $adresse) {
        Livewire::test(ForgotPassword::class)->set('email', $adresse)->call('sendLink')->assertSet('sent', true);
    }

    Notification::assertSentTo($zugang, SetPortalPassword::class, fn (SetPortalPassword $mail): bool => $mail->invitation === false);
    Notification::assertNotSentTo($gesperrt, SetPortalPassword::class);
});

it('verwaltet Zugaenge und Flux-Verknuepfung im Kundendetail', function (): void {
    Notification::fake();
    $this->actingAs(User::factory()->create());
    $kunde = Customer::factory()->create();

    Livewire::test(CustomerPortalAccess::class, ['customer' => $kunde])
        ->set('fluxContactId', '190')
        ->call('saveFluxContact')
        ->assertHasNoErrors()
        ->set('inviteName', 'Anna Kunde')
        ->set('inviteEmail', 'anna@example.test')
        ->call('invite')
        ->assertHasNoErrors()
        ->assertSee('anna@example.test');

    $zugang = PortalUser::query()->sole();
    expect($kunde->fresh()->flux_contact_id)->toBe(190);

    Livewire::test(CustomerPortalAccess::class, ['customer' => $kunde])->call('toggleActive', $zugang->id);
    expect($zugang->fresh()->is_active)->toBeFalse();

    $fremd = PortalUser::factory()->create();
    expect(fn () => Livewire::test(CustomerPortalAccess::class, ['customer' => $kunde])->call('toggleActive', $fremd->id))
        ->toThrow(ModelNotFoundException::class);
    expect($fremd->fresh()->is_active)->toBeTrue();

    $andererKunde = Customer::factory()->create(['flux_contact_id' => 300]);
    Livewire::test(CustomerPortalAccess::class, ['customer' => $kunde])
        ->set('fluxContactId', '300')
        ->call('saveFluxContact')
        ->assertHasErrors('flux_contact_id');
    expect($andererKunde->fresh()->flux_contact_id)->toBe(300);
});

it('zeigt den Reiter Kundenportal im Kundendetail', function (): void {
    $this->actingAs(User::factory()->create());
    $kunde = Customer::factory()->create();

    $this->get(route('customers.show', ['customer' => $kunde, 'bereich' => 'kundenportal']))
        ->assertOk()
        ->assertSee('Flux-Verknüpfung')
        ->assertSee('Portalzugänge');
});
