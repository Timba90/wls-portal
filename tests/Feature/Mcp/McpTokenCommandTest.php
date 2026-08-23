<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('stellt ein Token für einen Benutzer aus', function (): void {
    $user = User::factory()->create(['email' => 'merle@example.test']);

    $this->artisan('portal:mcp-token', [
        'aktion' => 'ausstellen',
        '--email' => 'merle@example.test',
        '--name' => 'Arbeitsplatz',
    ])->assertSuccessful();

    expect(PersonalAccessToken::query()
        ->where('tokenable_id', $user->id)
        ->where('name', 'Arbeitsplatz')
        ->exists())->toBeTrue();
});

it('versieht das Token mit einer Gültigkeit', function (): void {
    User::factory()->create(['email' => 'merle@example.test']);

    $this->artisan('portal:mcp-token', [
        'aktion' => 'ausstellen',
        '--email' => 'merle@example.test',
        '--tage' => '30',
    ])->assertSuccessful();

    expect(PersonalAccessToken::query()->first()?->expires_at)->not->toBeNull();
});

it('stellt auf Wunsch ein unbegrenzt gültiges Token aus', function (): void {
    User::factory()->create(['email' => 'merle@example.test']);

    $this->artisan('portal:mcp-token', [
        'aktion' => 'ausstellen',
        '--email' => 'merle@example.test',
        '--tage' => '0',
    ])->assertSuccessful();

    expect(PersonalAccessToken::query()->first()?->expires_at)->toBeNull();
});

it('lehnt eine unbekannte E-Mail-Adresse ab', function (): void {
    $this->artisan('portal:mcp-token', [
        'aktion' => 'ausstellen',
        '--email' => 'niemand@example.test',
    ])->assertFailed();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('widerruft ein Token über seine ID', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Arbeitsplatz');

    $this->artisan('portal:mcp-token', [
        'aktion' => 'widerrufen',
        '--id' => (string) $token->accessToken->id,
    ])->assertSuccessful();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('listet die vorhandenen Tokens auf', function (): void {
    $user = User::factory()->create(['email' => 'merle@example.test']);
    $user->createToken('Arbeitsplatz');

    $this->artisan('portal:mcp-token', ['aktion' => 'auflisten'])
        ->expectsOutputToContain('Arbeitsplatz')
        ->assertSuccessful();
});

it('weist eine unbekannte Aktion ab', function (): void {
    $this->artisan('portal:mcp-token', ['aktion' => 'irgendwas'])->assertFailed();
});
