<?php

use App\Actions\Registrar\ImportRegistrarInventory;
use App\Enums\RegistrarProvider;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\IntegrationCredential;
use App\Support\Registrar\AutoDnsClient;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Antworten, wie autoDNS sie liefert.
 *
 * Umschlag und Feldnamen stammen aus der offiziellen OpenAPI-Beschreibung
 * (InterNetX/domainrobot-api, `src/domainrobot.json`) — sie sind nicht geraten.
 *
 * @param  array<int, array<string, mixed>>  $daten
 * @return array<string, mixed>
 */
function autodnsAntwort(array $daten = [], string $typ = 'SUCCESS', string $code = 'S0301', string $text = 'Suche erfolgreich.'): array
{
    return [
        'stid' => '20260826-app1-dev',
        'status' => ['code' => $code, 'type' => $typ, 'text' => $text],
        'object' => ['type' => 'Domain', 'value' => 'suche'],
        'data' => $daten,
    ];
}

/**
 * Eine Attrappe für alle drei Pfade. `Http::fake()` ersetzt eine bestehende
 * nicht, sondern reiht sich dahinter ein — der Bestand liegt deshalb in einem
 * Behälter, den ein zweiter Aufruf umschreibt.
 */
$GLOBALS['autodnsBestand'] = ['domains' => [], 'certificates' => []];

function autodnsFake(array $domains = [], array $certificates = []): void
{
    $GLOBALS['autodnsBestand'] = ['domains' => $domains, 'certificates' => $certificates];

    Http::fake(function (Request $anfrage): array {
        $pfad = $anfrage->url();
        $offset = (int) ($anfrage->data()['view']['offset'] ?? 0);
        $bestand = $GLOBALS['autodnsBestand'];

        return match (true) {
            str_contains($pfad, '/hello') => autodnsAntwort(code: 'S0101', text: 'Hallo.'),
            str_contains($pfad, 'domain/_search') => autodnsAntwort($offset === 0 ? $bestand['domains'] : []),
            str_contains($pfad, 'certificate/_search') => autodnsAntwort($offset === 0 ? $bestand['certificates'] : []),
            default => autodnsAntwort(),
        };
    });
}

function autodnsClient(): AutoDnsClient
{
    return new AutoDnsClient([
        'endpoint' => 'https://api.example.test/v1/',
        'username' => 'benutzer',
        'password' => 'geheim',
        'context' => '4',
    ]);
}

it('meldet einen Anschluss ohne Zugangsdaten, statt es zu versuchen', function (): void {
    $client = new AutoDnsClient(['endpoint' => 'https://api.example.test/v1/']);

    expect($client->isConfigured())->toBeFalse();

    app(ImportRegistrarInventory::class)($client);
})->throws(RegistrarException::class, 'keine Zugangsdaten hinterlegt');

it('haelt einen Anschluss ohne Kontext fuer unvollstaendig', function (): void {
    // Ohne Kontext weiß autoDNS nicht, gegen welches System der Aufruf geht.
    $client = new AutoDnsClient([
        'endpoint' => 'https://api.example.test/v1/',
        'username' => 'benutzer',
        'password' => 'geheim',
    ]);

    expect($client->isConfigured())->toBeFalse();
});

it('prueft den Zugang ueber hello, ohne etwas zu lesen', function (): void {
    autodnsFake();

    expect(autodnsClient()->testConnection())->toContain('Hallo.');

    Http::assertSent(fn (Request $anfrage): bool => str_ends_with($anfrage->url(), '/hello')
        && $anfrage->method() === 'GET');

    // Der Test darf keine Suche auslösen.
    Http::assertNotSent(fn (Request $anfrage): bool => str_contains($anfrage->url(), '_search'));
});

it('meldet beim Verbindungstest die Ablehnung des Anbieters im Wortlaut', function (): void {
    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'EF01001', 'type' => 'ERROR', 'text' => 'Authorization failed'],
    ], 401));

    autodnsClient()->testConnection();
})->throws(RegistrarException::class, 'Authorization failed');

it('sendet Zugangsdaten und Kontext im Kopf mit', function (): void {
    autodnsFake();

    autodnsClient()->testConnection();

    Http::assertSent(fn (Request $anfrage): bool => $anfrage->hasHeader('X-Domainrobot-Context', '4')
        && $anfrage->hasHeader('Authorization'));
});

it('liest Domains von autoDNS ein', function (): void {
    autodnsFake(domains: [[
        'name' => 'Beispiel.DE',
        'registryStatus' => 'ACTIVE',
        'domainCreated' => '2021-03-04T10:00:00.000+0100',
        'created' => '2024-01-01T10:00:00.000+0100',
        'expire' => '2027-03-04T10:00:00.000+0100',
        'autoRenewStatus' => 'TRUE',
        'nameServers' => [['name' => 'ns1.example.net'], ['name' => 'ns2.example.net']],
    ]]);

    $ergebnis = app(ImportRegistrarInventory::class)(autodnsClient());

    expect($ergebnis['domains'])->toBe(['new' => 1, 'updated' => 0]);

    $domain = Domain::query()->sole();

    expect($domain->name)->toBe('beispiel.de')
        ->and($domain->provider)->toBe(RegistrarProvider::AutoDns)
        ->and($domain->status)->toBe('ACTIVE')
        // `domainCreated` ist das Datum bei der Registrierungsstelle und geht vor.
        ->and($domain->registered_on->toDateString())->toBe('2021-03-04')
        ->and($domain->expires_on->toDateString())->toBe('2027-03-04')
        ->and($domain->auto_renew)->toBeTrue()
        ->and($domain->nameservers)->toBe(['ns1.example.net', 'ns2.example.net'])
        ->and($domain->synced_at)->not->toBeNull();
});

it('haelt nur TRUE fuer eine dauerhafte Verlaengerung', function (): void {
    // autoDNS kennt TRUE, FALSE und ONCE — ONCE verlängert genau einmal.
    autodnsFake(domains: [
        ['name' => 'einmal.de', 'autoRenewStatus' => 'ONCE'],
        ['name' => 'nie.de', 'autoRenewStatus' => 'FALSE'],
    ]);

    app(ImportRegistrarInventory::class)(autodnsClient());

    expect(Domain::query()->where('name', 'einmal.de')->sole()->auto_renew)->toBeFalse()
        ->and(Domain::query()->where('name', 'nie.de')->sole()->auto_renew)->toBeFalse();
});

it('liest Zertifikate von autoDNS ein', function (): void {
    autodnsFake(certificates: [[
        'id' => 4711,
        'name' => 'www.Beispiel.de',
        'product' => 'SSL123',
        'created' => '2026-01-10T00:00:00.000+0100',
        'expire' => '2027-01-10T00:00:00.000+0100',
        'subjectAlternativeNames' => [['name' => 'beispiel.de'], ['name' => 'shop.beispiel.de']],
    ]]);

    app(ImportRegistrarInventory::class)(autodnsClient());

    $zertifikat = Certificate::query()->sole();

    expect($zertifikat->common_name)->toBe('www.beispiel.de')
        ->and($zertifikat->provider_reference)->toBe('4711')
        ->and($zertifikat->issuer)->toBe('SSL123')
        ->and($zertifikat->expires_on->toDateString())->toBe('2027-01-10')
        ->and($zertifikat->alternative_names)->toBe(['beispiel.de', 'shop.beispiel.de']);
});

it('legt beim zweiten Lauf keine Dublette an, sondern gleicht ab', function (): void {
    autodnsFake(domains: [[
        'name' => 'beispiel.de', 'registryStatus' => 'ACTIVE',
        'expire' => '2027-03-04T10:00:00.000+0100',
    ]]);

    app(ImportRegistrarInventory::class)(autodnsClient());

    autodnsFake(domains: [[
        'name' => 'beispiel.de', 'registryStatus' => 'LOCK',
        'expire' => '2028-03-04T10:00:00.000+0100',
    ]]);

    $ergebnis = app(ImportRegistrarInventory::class)(autodnsClient());

    expect($ergebnis['domains'])->toBe(['new' => 0, 'updated' => 1])
        ->and(Domain::query()->count())->toBe(1);

    $domain = Domain::query()->sole();

    expect($domain->status)->toBe('LOCK')
        ->and($domain->expires_on->toDateString())->toBe('2028-03-04');
});

it('wirft die von Hand gesetzte Zuordnung beim Abgleich nicht weg', function (): void {
    $kunde = Customer::factory()->create();

    Domain::factory()->create([
        'name' => 'beispiel.de',
        'customer_id' => $kunde->id,
        'expires_on' => '2026-01-01',
    ]);

    autodnsFake(domains: [['name' => 'beispiel.de', 'expire' => '2029-03-04T10:00:00.000+0100']]);

    app(ImportRegistrarInventory::class)(autodnsClient());

    $domain = Domain::query()->sole();

    // Der Registrar kennt unsere Kunden nicht — er darf sie nicht überschreiben.
    expect($domain->customer_id)->toBe($kunde->id)
        ->and($domain->expires_on->toDateString())->toBe('2029-03-04');
});

it('schreibt im Trockenlauf nichts', function (): void {
    autodnsFake(domains: [['name' => 'beispiel.de', 'expire' => '2027-03-04T10:00:00.000+0100']]);

    $ergebnis = app(ImportRegistrarInventory::class)(autodnsClient(), dryRun: true);

    expect($ergebnis['domains'])->toBe(['new' => 1, 'updated' => 0])
        ->and(Domain::query()->count())->toBe(0);
});

it('bricht mit der Meldung des Anbieters ab, wenn autoDNS einen Fehler liefert', function (): void {
    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'EF01001', 'type' => 'ERROR', 'text' => 'Authorization failed'],
    ], 401));

    app(ImportRegistrarInventory::class)(autodnsClient());
})->throws(RegistrarException::class, 'Authorization failed');

it('bricht ab, wenn ein Domaineintrag keinen Namen hat', function (): void {
    autodnsFake(domains: [['registryStatus' => 'ACTIVE']]);

    app(ImportRegistrarInventory::class)(autodnsClient());
})->throws(RegistrarException::class, 'ohne Namen');

it('uebergeht Zertifikate ohne Kennung des Anbieters, statt Dubletten anzulegen', function (): void {
    autodnsFake(certificates: [['name' => 'ohne-kennung.de']]);

    $ergebnis = app(ImportRegistrarInventory::class)(autodnsClient());

    expect($ergebnis['skipped'])->toBe(1)
        ->and(Certificate::query()->count())->toBe(0);
});

it('blaettert, bis eine Seite nicht mehr voll ist', function (): void {
    // Zwei volle Seiten wären zweihundert Einträge; hier reicht der Beleg,
    // dass nach einer nicht vollen Seite Schluss ist.
    autodnsFake(domains: [
        ['name' => 'eins.de'],
        ['name' => 'zwei.de'],
    ]);

    app(ImportRegistrarInventory::class)(autodnsClient());

    expect(Domain::query()->count())->toBe(2);

    Http::assertSentCount(2);
});

it('nennt bei einem unbekannten Anbieter nur den Tippfehler', function (): void {
    // Ein Tippfehler im Namen ist etwas anderes als ein fehlender Zugang;
    // beide Meldungen zusammen führten in die falsche Richtung.
    foreach (['registrar:test', 'registrar:import'] as $befehl) {
        $this->artisan($befehl, ['anbieter' => 'inwx'])
            ->expectsOutputToContain('Unbekannter Anbieter')
            ->doesntExpectOutputToContain('Kein Anbieter ist eingerichtet')
            ->assertFailed();
    }
});

it('liefert nur eingerichtete Anbieter', function (): void {
    expect(app(RegistrarClientFactory::class)->configured())->toBe([]);

    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim', 'context' => '4'],
    ]);

    $anbieter = array_map(
        fn ($client) => $client->provider(),
        app(RegistrarClientFactory::class)->configured(),
    );

    expect($anbieter)->toBe([RegistrarProvider::AutoDns]);
});
