<?php

use App\Actions\Auth\TerminateSession;
use App\Livewire\Profile\SecurityPage;
use App\Models\User;
use App\Models\UserSession;
use Livewire\Livewire;

it('protokolliert die Sitzung eines angemeldeten Benutzers', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(UserSession::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('protokolliert keine Sitzung fuer nicht angemeldete Besucher', function (): void {
    $this->get(route('login'))->assertOk();

    expect(UserSession::query()->count())->toBe(0);
});

it('listet die Sitzungen des Benutzers auf der Sicherheitsseite', function (): void {
    $user = User::factory()->create();

    UserSession::query()->create([
        'id' => 'sitzung-eins',
        'user_id' => $user->id,
        'ip_address' => '198.51.100.10',
        'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/120',
        'last_activity' => time(),
    ]);

    Livewire::actingAs($user)
        ->test(SecurityPage::class)
        ->assertSee('198.51.100.10')
        ->assertSee('Chrome · macOS');
});

it('beendet eine einzelne Sitzung', function (): void {
    $user = User::factory()->create();

    UserSession::query()->create([
        'id' => 'sitzung-zwei',
        'user_id' => $user->id,
        'ip_address' => '198.51.100.11',
        'user_agent' => 'Mozilla/5.0 (Windows) Firefox/130',
        'last_activity' => time(),
    ]);

    Livewire::actingAs($user)
        ->test(SecurityPage::class)
        ->call('terminateSession', 'sitzung-zwei');

    expect(UserSession::query()->whereKey('sitzung-zwei')->exists())->toBeFalse();
});

it('beendet keine Sitzung eines fremden Benutzers', function (): void {
    $user = User::factory()->create();
    $fremd = User::factory()->create();

    UserSession::query()->create([
        'id' => 'fremde-sitzung',
        'user_id' => $fremd->id,
        'ip_address' => '198.51.100.12',
        'user_agent' => 'Mozilla/5.0 (Linux)',
        'last_activity' => time(),
    ]);

    app(TerminateSession::class)($user, 'fremde-sitzung');

    expect(UserSession::query()->whereKey('fremde-sitzung')->exists())->toBeTrue();
});

it('beendet alle Sitzungen ausser der aktuellen', function (): void {
    $user = User::factory()->create();

    collect(['sitzung-a', 'sitzung-b', 'sitzung-c'])->each(fn (string $id) => UserSession::query()->create([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '198.51.100.20',
        'user_agent' => 'Mozilla/5.0 (Windows)',
        'last_activity' => time(),
    ]));

    $this->actingAs($user);

    UserSession::query()->create([
        'id' => session()->getId(),
        'user_id' => $user->id,
        'ip_address' => '198.51.100.21',
        'user_agent' => 'Mozilla/5.0 (Macintosh)',
        'last_activity' => time(),
    ]);

    app(TerminateSession::class)->allExceptCurrent($user);

    expect(UserSession::query()->where('user_id', $user->id)->pluck('id')->all())
        ->toBe([session()->getId()]);
});
