<?php

use App\Actions\Flux\SaveFluxCredentials;
use App\Livewire\System\FluxLedgerAccountList;
use App\Livewire\System\IntegrationSettings;
use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('keeps the saved token out of markup and component state', function () {
    $user = User::factory()->create();
    app(SaveFluxCredentials::class)->handle(['base_url' => 'https://flux.weblab-studio.de/api', 'token' => 'secret-hidden', 'tenant_id' => '1'], $user->id);
    Livewire::actingAs($user)->test(IntegrationSettings::class)
        ->assertSet('fluxInput.token', '')->assertDontSee('secret-hidden')->assertSee('Flux');
});

it('saves and removes Flux access through the UI', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user)->test(IntegrationSettings::class)
        ->set('fluxInput.token', 'secret-hidden')->set('fluxInput.tenant_id', '1')
        ->call('saveFlux')->assertDispatched('zugang-gespeichert')->assertSet('fluxInput.token', '')
        ->call('forgetFlux')->assertDispatched('zugang-entfernt');
    expect(IntegrationCredential::valuesFor('flux'))->toBe([]);
});

it('protects the ledger page and credential mutations from guests', function () {
    test()->get('/sachkonten')->assertRedirect('/login');
    Livewire::test(IntegrationSettings::class)->call('forgetFlux')->assertForbidden();
});

it('shows an empty ledger page without issuing API requests', function () {
    Http::fake();
    Livewire::actingAs(User::factory()->create())->test(FluxLedgerAccountList::class)->assertSee('Sachkonten');
    Http::assertNothingSent();
});

it('creates an account through the form and renders its stored type', function () {
    $user = User::factory()->create();
    app(SaveFluxCredentials::class)->handle(['base_url' => 'https://flux.weblab-studio.de/api', 'token' => 'test-secret', 'tenant_id' => '1'], $user->id);
    $remote = ['id' => 10, 'tenant_id' => 1, 'number' => '6400', 'name' => 'Versicherungen', 'ledger_account_type_enum' => 'expense', 'is_automatic' => false];
    Http::fake(['*' => Http::sequence()->push(['data' => ['data' => [], 'current_page' => 1, 'last_page' => 1]])->push(['data' => $remote], 201)]);
    Livewire::actingAs($user)->test(FluxLedgerAccountList::class)
        ->set('input.number', '6400')->set('input.name', 'Versicherungen')->set('input.type', 'expense')
        ->call('create')->assertHasNoErrors()->assertSee('Versicherungen')->assertSee('Aufwand');
    Http::assertSentCount(2);
});

it('rejects manipulated registrar providers without deleting Flux access', function () {
    $user = User::factory()->create();
    app(SaveFluxCredentials::class)->handle(['base_url' => 'https://flux.weblab-studio.de/api', 'token' => 'test-secret', 'tenant_id' => '1'], $user->id);
    Livewire::actingAs($user)->test(IntegrationSettings::class)->call('forget', 'flux')->assertStatus(422);
    expect(IntegrationCredential::valuesFor('flux'))->not->toBeEmpty();
});
it('obtains and stores a token without persisting login credentials', function () {
    $user = User::factory()->create();
    Http::fake(['*/auth/token' => Http::response(['status' => 200, 'access_token' => 'new-secret-token'])]);
    Livewire::actingAs($user)->test(IntegrationSettings::class)
        ->set('fluxInput.tenant_id', '1')->set('fluxLogin.username', 'user@example.com')->set('fluxLogin.password', ' exact password ')
        ->call('loginFlux')->assertDispatched('zugang-gespeichert')
        ->assertSet('fluxLogin.password', '')->assertSet('fluxLogin.username', '')->assertSet('fluxInput.token', '')
        ->assertDontSee('new-secret-token');
    expect(IntegrationCredential::valuesFor('flux'))->toEqual(['base_url' => 'https://flux.weblab-studio.de/api', 'token' => 'new-secret-token', 'tenant_id' => '1']);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['username'] === 'user@example.com' && $request['password'] === ' exact password ' && ! $request->hasHeader('Authorization'));
});

it('clears login secrets and retains the existing token when authentication fails', function () {
    $user = User::factory()->create();
    app(SaveFluxCredentials::class)->handle(['base_url' => 'https://flux.weblab-studio.de/api', 'token' => 'old-token', 'tenant_id' => '1'], $user->id);
    Http::fake(['*' => Http::response(['password' => 'sensitive-password'], 401)]);
    Livewire::actingAs($user)->test(IntegrationSettings::class)
        ->set('fluxLogin.username', 'user@example.com')->set('fluxLogin.password', 'sensitive-password')
        ->call('loginFlux')->assertDispatched('zugang-abgelehnt')->assertSet('fluxLogin.password', '')->assertDontSee('sensitive-password');
    expect(IntegrationCredential::valuesFor('flux')['token'])->toBe('old-token');
    Http::assertSentCount(1);
});
it('does not transmit login credentials to a foreign host', function () {
    Http::fake();
    Livewire::actingAs(User::factory()->create())->test(IntegrationSettings::class)
        ->set('fluxInput.base_url', 'https://other.example/api')->set('fluxInput.tenant_id', '1')
        ->set('fluxLogin.username', 'user@example.com')->set('fluxLogin.password', 'secret-password')
        ->call('loginFlux')->assertDispatched('zugang-abgelehnt')->assertSet('fluxLogin.password', '');
    Http::assertNothingSent();
});

it('does not follow login redirects or persist malformed tokens', function ($status, $body) {
    $user = User::factory()->create();
    Http::fake(['*' => Http::response($body, $status, ['Location' => 'https://other.example'])]);
    Livewire::actingAs($user)->test(IntegrationSettings::class)
        ->set('fluxInput.tenant_id', '1')->set('fluxLogin.username', 'user@example.com')->set('fluxLogin.password', 'secret-password')
        ->call('loginFlux')->assertDispatched('zugang-abgelehnt')->assertSet('fluxLogin.password', '');
    expect(IntegrationCredential::valuesFor('flux'))->toBe([]);
    Http::assertSentCount(1);
})->with([[302, []], [200, ['status' => 200]], [200, ['status' => 200, 'access_token' => "bad\ntoken"]]]);

it('rejects guest login attempts', function () {
    Http::fake();
    Livewire::test(IntegrationSettings::class)->call('loginFlux')->assertForbidden();
    Http::assertNothingSent();
});
