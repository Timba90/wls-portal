<?php

use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Support\Facades\Http;

function fluxTestClient(): FluxClient
{
    return new FluxClient(['base_url' => 'https://flux.weblab-studio.de/api/', 'token' => 'secret-test', 'tenant_id' => '1']);
}

it('reads all pages with bearer authentication', function () {
    Http::fake(['*/ledger-accounts*' => Http::sequence()
        ->push(['data' => ['data' => [['id' => 10, 'tenant_id' => 1, 'number' => '6400', 'name' => 'Versicherungen', 'ledger_account_type_enum' => 'expense', 'is_automatic' => false]], 'current_page' => 1, 'last_page' => 2]])
        ->push(['data' => ['data' => [], 'current_page' => 2, 'last_page' => 2]])]);
    expect(fluxTestClient()->ledgerAccounts())->toHaveCount(1);
    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('Authorization', 'Bearer secret-test') && ! str_contains($r->url(), '/api/api'));
});

it('rejects a foreign host before sending the token', function () {
    Http::fake();
    expect(fn () => new FluxClient(['base_url' => 'https://evil.test/api', 'token' => 'secret', 'tenant_id' => 1]))->toThrow(FluxException::class);
    Http::assertNothingSent();
});

it('never exposes response bodies or tokens in errors', function (int $status) {
    Http::fake(['*' => Http::response('secret-test', $status)]);
    try {
        fluxTestClient()->ledgerAccounts();
        test()->fail('Expected exception');
    } catch (FluxException $e) {
        expect($e->getMessage())->not->toContain('secret-test');
    }
})->with([302, 401, 403, 422, 429, 500]);

it('rejects malformed pagination instead of returning partial data', function () {
    Http::fake(['*' => Http::response(['data' => ['data' => [], 'current_page' => 2, 'last_page' => 2]])]);
    expect(fn () => fluxTestClient()->ledgerAccounts())->toThrow(FluxException::class);
});

it('creates only one request with explicit tenant and account type', function () {
    $account = ['id' => 15, 'tenant_id' => 1, 'number' => '6400', 'name' => 'Versicherungen', 'ledger_account_type_enum' => 'expense', 'is_automatic' => false];
    Http::fake(['*' => Http::response(['data' => $account], 201)]);
    expect(fluxTestClient()->createLedgerAccount($account)['id'])->toBe(15);
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['tenant_id'] === 1);
});
it('stops an expired creation preflight before issuing any request', function () {
    Http::fake();
    expect(fn () => fluxTestClient()->ledgerAccounts(hrtime(true) / 1e9 - 1))->toThrow(FluxException::class);
    Http::assertNothingSent();
});
