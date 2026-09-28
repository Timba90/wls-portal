<?php

use App\Actions\Registrar\PlanDnsChange;
use App\Enums\RegistrarProvider;
use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Registrar\DnsAenderungAnwenden;
use App\Mcp\Tools\Registrar\DnsAenderungPlanen;
use App\Mcp\Tools\Registrar\DnsZoneLesen;
use App\Models\DnsChange;
use App\Models\Domain;
use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Die drei DNS-Werkzeuge des MCP-Servers.
 *
 * Lesen, planen, anwenden — in dieser Reihenfolge und nicht anders: ohne
 * Prüfsumme aus dem Plan ändert das letzte nichts.
 */
beforeEach(function (): void {
    $this->benutzer = User::factory()->create();

    config()->set('portal.dns.writes_enabled', true);
    config()->set('portal.dns.cache_minutes', 0);

    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::ResellerInterface->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    $this->domain = Domain::factory()->create([
        'name' => 'aguamix.at',
        'provider' => RegistrarProvider::ResellerInterface,
    ]);
});

function mcpDnsFake(): void
{
    Http::fake([
        '*/reseller/login' => Http::response(
            ['state' => 1000, 'stateName' => 'OK'],
            200,
            ['Set-Cookie' => 'coreSID=sitzung-123; Path=/; HttpOnly'],
        ),
        '*/dns/getZoneDetails' => Http::response([
            'state' => 1000,
            'domain' => 'aguamix.at',
            'soa' => ['mail' => 'hostmaster@aguamix.at', 'ttl' => 3600],
            'records' => [
                '13' => ['id' => 13, 'name' => '_dmarc', 'ttl' => 3600, 'type' => 'TXT', 'priority' => 0, 'content' => 'v=DMARC1; p=none'],
            ],
            'total' => 1,
            'vns' => ['hostname' => ['ns1.example.net']],
        ]),
        '*/dns/createBackup' => Http::response(['state' => 1000]),
        '*/dns/updateRecord' => Http::response(['state' => 1000]),
    ]);
}

it('liest die Zone und nennt die Kennung je Eintrag', function (): void {
    mcpDnsFake();

    PortalServer::actingAs($this->benutzer)
        ->tool(DnsZoneLesen::class, ['domain' => 'AguaMix.AT'])
        ->assertOk()
        ->assertSee('_dmarc')
        ->assertSee('v=DMARC1; p=none')
        // Die Kennung ist der Anker für eine Änderung.
        ->assertSee('"kennung":"13"');
});

it('lehnt eine Domain ab, die nicht im Bestand steht', function (): void {
    Http::fake();

    PortalServer::actingAs($this->benutzer)
        ->tool(DnsZoneLesen::class, ['domain' => 'fremde.de'])
        ->assertHasErrors();

    Http::assertNothingSent();
});

it('plant eine Aenderung, ohne etwas zu aendern', function (): void {
    mcpDnsFake();

    PortalServer::actingAs($this->benutzer)
        ->tool(DnsAenderungPlanen::class, [
            'domain' => 'aguamix.at',
            'vorgang' => 'aendern',
            'name' => '_dmarc',
            'typ' => 'TXT',
            'inhalt' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@itm-technologies.de',
        ])
        ->assertOk()
        ->assertSee('pruefsumme')
        ->assertSee('p=quarantine');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'updateRecord'));
});

it('verlangt den Domainnamen als Bestaetigung', function (): void {
    mcpDnsFake();

    PortalServer::actingAs($this->benutzer)
        ->tool(DnsAenderungAnwenden::class, [
            'domain' => 'aguamix.at',
            'vorgang' => 'aendern',
            'name' => '_dmarc',
            'typ' => 'TXT',
            'inhalt' => 'v=DMARC1; p=quarantine',
            'pruefsumme' => str_repeat('a', 64),
            'bestaetigung' => 'ja bitte',
        ])
        ->assertHasErrors();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'updateRecord'));
});

it('lehnt eine falsche Pruefsumme ab', function (): void {
    mcpDnsFake();

    PortalServer::actingAs($this->benutzer)
        ->tool(DnsAenderungAnwenden::class, [
            'domain' => 'aguamix.at',
            'vorgang' => 'aendern',
            'name' => '_dmarc',
            'typ' => 'TXT',
            'inhalt' => 'v=DMARC1; p=quarantine',
            'pruefsumme' => str_repeat('0', 64),
            'bestaetigung' => 'aguamix.at',
        ])
        ->assertHasErrors();

    expect(DnsChange::query()->count())->toBe(0);
});

it('wendet die geplante Aenderung an und protokolliert sie', function (): void {
    mcpDnsFake();

    $eingabe = [
        'domain' => 'aguamix.at',
        'vorgang' => 'aendern',
        'name' => '_dmarc',
        'typ' => 'TXT',
        'inhalt' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@itm-technologies.de',
    ];

    $plan = app(PlanDnsChange::class)($this->domain, $eingabe);

    PortalServer::actingAs($this->benutzer)
        ->tool(DnsAenderungAnwenden::class, [
            ...$eingabe,
            'pruefsumme' => $plan->fingerprint,
            'bestaetigung' => 'AguaMix.at',
        ])
        ->assertOk()
        ->assertSee('protokoll_id');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'dns/updateRecord')
        && $request['id'] === '13');

    $protokoll = DnsChange::query()->sole();

    expect($protokoll->record_name)->toBe('_dmarc')
        ->and($protokoll->after)->toContain('p=quarantine')
        ->and($protokoll->user_id)->toBe($this->benutzer->id);
});
