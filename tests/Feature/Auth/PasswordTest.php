<?php

use App\Livewire\Profile\ProfilePage;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('verlangt mindestens zwoelf Zeichen mit Gross- und Kleinbuchstaben, Zahl und Sonderzeichen', function (string $password): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ProfilePage::class)
        ->set('current_password', 'GeheimesPasswort1!')
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('updatePassword')
        ->assertHasErrors('password');
})->with([
    'zu kurz' => 'Kurz1!x',
    'ohne Grossbuchstaben' => 'ohnegross123!',
    'ohne Kleinbuchstaben' => 'OHNEKLEIN123!',
    'ohne Zahl' => 'OhneZahlen!!!',
    'ohne Sonderzeichen' => 'OhneSonder123',
]);

it('akzeptiert ein regelkonformes Passwort', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->set('current_password', 'GeheimesPasswort1!')
        ->set('password', 'NeuesGeheimes1!')
        ->set('password_confirmation', 'NeuesGeheimes1!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('NeuesGeheimes1!', $user->fresh()->password))->toBeTrue();
});

it('lehnt eine Passwortaenderung mit falschem aktuellem Passwort ab', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ProfilePage::class)
        ->set('current_password', 'falschesPasswort1!')
        ->set('password', 'NeuesGeheimes1!')
        ->set('password_confirmation', 'NeuesGeheimes1!')
        ->call('updatePassword')
        ->assertHasErrors('current_password');
});

it('verschickt einen Link zum Zuruecksetzen des Passworts', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('setzt das Passwort ueber einen gueltigen Link zurueck', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'GanzNeuesPasswort1!',
            'password_confirmation' => 'GanzNeuesPasswort1!',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect(Hash::check('GanzNeuesPasswort1!', $user->fresh()->password))->toBeTrue();
});
