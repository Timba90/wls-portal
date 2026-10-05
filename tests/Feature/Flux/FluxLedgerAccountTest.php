<?php

use App\Actions\Flux\CreateFluxLedgerAccount;
use App\Actions\Flux\SaveFluxCredentials;
use App\Actions\Flux\SyncFluxLedgerAccounts;
use App\Models\FluxLedgerAccount;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Support\Flux\FluxException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(SaveFluxCredentials::class)->handle(['base_url' => 'https://flux.weblab-studio.de/api', 'token' => 'test-secret', 'tenant_id' => '1'], $this->user->id);
    $this->account = ['id' => 10, 'tenant_id' => 1, 'number' => '6400', 'name' => 'Versicherungen', 'ledger_account_type_enum' => 'expense', 'is_automatic' => false];
    $this->page = fn ($accounts) => ['data' => ['data' => $accounts, 'current_page' => 1, 'last_page' => 1]];
    $this->input = ['number' => '6400', 'name' => 'Versicherungen', 'type' => 'expense', 'is_automatic' => false];
});

it('encrypts credentials and preserves an omitted token', function () {
    app(SaveFluxCredentials::class)->handle(['token' => '', 'tenant_id' => '2'], $this->user->id);
    expect(IntegrationCredential::valuesFor('flux')['token'])->toBe('test-secret')
        ->and(DB::table('integration_credentials')->value('credentials'))->not->toContain('test-secret');
});

it('links an existing account without a POST', function () {
    Http::fake(['*' => Http::response(($this->page)([$this->account]))]);
    expect(app(CreateFluxLedgerAccount::class)->handle($this->input, $this->user->id)->flux_id)->toBe(10);
    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});

it('creates a missing account and stores its confirmed id', function () {
    Http::fake(['*' => Http::sequence()->push(($this->page)([]))->push(['data' => $this->account], 201)]);
    expect(app(CreateFluxLedgerAccount::class)->handle($this->input, $this->user->id)->flux_id)->toBe(10);
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['ledger_account_type_enum'] === 'expense' && $r['tenant_id'] === 1);
});

it('recovers a lost create response without a second POST', function () {
    Http::fake(['*' => Http::sequence()->push(($this->page)([]))->push('bad', 500)->push(($this->page)([$this->account]))]);
    expect(app(CreateFluxLedgerAccount::class)->handle($this->input, $this->user->id)->flux_id)->toBe(10);
    Http::assertSentCount(3);
});

it('rejects a conflicting type', function () {
    Http::fake(['*' => Http::response(($this->page)([array_replace($this->account, ['ledger_account_type_enum' => 'asset'])]))]);
    expect(fn () => app(CreateFluxLedgerAccount::class)->handle($this->input, $this->user->id))->toThrow(FluxException::class);
    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});

it('syncs only the selected tenant and can be repeated without duplicates', function () {
    Http::fake(['*' => Http::response(($this->page)([$this->account, array_replace($this->account, ['id' => 20, 'tenant_id' => 2])]))]);
    app(SyncFluxLedgerAccounts::class)->handle($this->user->id);
    app(SyncFluxLedgerAccounts::class)->handle($this->user->id);
    expect(FluxLedgerAccount::count())->toBe(1)->and(FluxLedgerAccount::first()->tenant_id)->toBe(1);
});
