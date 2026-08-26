<?php

use App\Actions\Registrar\ImportRegistrarInventory;
use App\Enums\RegistrarProvider;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\IntegrationCredential;
use App\Support\Registrar\DomainResellingClient;
use App\Support\Registrar\InwxClient;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Antworten, wie die Anbieter sie liefern.
 *
 * Die Feldnamen stammen aus der Dokumentation der beiden Schnittstellen. Sie
 * gegen echte Konten zu prüfen ist Sache des Trockenlaufs — hier steht fest,
 * dass die Anschlüsse diese Form korrekt lesen und alles andere melden.
 */
function inwxAntwort(array $resData, int $code = 1000): array
{
    return ['code' => $code, 'msg' => 'Command completed successfully', 'resData' => $resData];
}

/**
 * Die Attrappe wird einmal je Test gesetzt; `Http::fake()` ersetzt eine
 * bestehende nicht, sondern reiht sich dahinter ein. Der Bestand liegt deshalb
 * in einem Behälter, den ein zweiter Aufruf umschreibt.
 *
 * @var array{domains: array<int, array<string, mixed>>, certificates: array<int, array<string, mixed>>}
 */
$GLOBALS['inwxBestand'] = ['domains' => [], 'certificates' => []];

function inwxFake(array $domains = [], array $certificates = []): void
{
    $GLOBALS['inwxBestand'] = ['domains' => $domains, 'certificates' => $certificates];

    Http::fake(function (Request $anfrage): array {
        $methode = $anfrage->data()['method'] ?? '';
        $seite = (int) ($anfrage->data()['params']['page'] ?? 1);
        $bestand = $GLOBALS['inwxBestand'];

        return match (true) {
            $methode === 'account.login' => inwxAntwort(['tfa' => '0']),
            $methode === 'domain.list' => inwxAntwort([
                'count' => count($bestand['domains']),
                'domain' => $seite === 1 ? $bestand['domains'] : [],
            ]),
            $methode === 'certificate.list' => inwxAntwort([
                'count' => count($bestand['certificates']),
                'certificate' => $seite === 1 ? $bestand['certificates'] : [],
            ]),
            default => inwxAntwort([]),
        };
    });
}

function resellingAntwort(array $eigenschaften, int $code = 200, string $beschreibung = 'Command completed successfully'): string
{
    $zeilen = ['[RESPONSE]', "code = {$code}", "description = {$beschreibung}"];

    foreach ($eigenschaften as $name => $werte) {
        foreach ((array) $werte as $index => $wert) {
            $zeilen[] = "property[{$name}][{$index}] = {$wert}";
        }
    }

    return implode("\n", [...$zeilen, 'EOF', '']);
}

function inwxClient(): InwxClient
{
    return new InwxClient([
        'endpoint' => 'https://api.example.test/jsonrpc/',
        'username' => 'benutzer',
        'password' => 'geheim',
    ]);
}

it('meldet einen Anschluss ohne Zugangsdaten, statt es zu versuchen', function (): void {
    $client = new InwxClient(['endpoint' => 'https://api.example.test/jsonrpc/']);

    expect($client->isConfigured())->toBeFalse();

    app(ImportRegistrarInventory::class)($client);
})->throws(RegistrarException::class, 'keine Zugangsdaten');

it('liest Domains von INWX ein', function (): void {
    inwxFake(domains: [[
        'roId' => 4711,
        'domain' => 'Beispiel.DE',
        'status' => 'OK',
        'crDate' => '2021-03-04 10:00:00',
        'exDate' => '2027-03-04 10:00:00',
        'renewalMode' => 'AUTORENEW',
        'ns' => ['ns1.example.net', 'ns2.example.net'],
    ]]);

    $ergebnis = app(ImportRegistrarInventory::class)(inwxClient());

    expect($ergebnis['domains'])->toBe(['new' => 1, 'updated' => 0]);

    $domain = Domain::query()->sole();

    expect($domain->name)->toBe('beispiel.de')
        ->and($domain->provider)->toBe(RegistrarProvider::Inwx)
        ->and($domain->provider_reference)->toBe('4711')
        ->and($domain->expires_on->toDateString())->toBe('2027-03-04')
        ->and($domain->registered_on->toDateString())->toBe('2021-03-04')
        ->and($domain->auto_renew)->toBeTrue()
        ->and($domain->nameservers)->toBe(['ns1.example.net', 'ns2.example.net'])
        ->and($domain->synced_at)->not->toBeNull();
});

it('liest Zertifikate von INWX ein', function (): void {
    inwxFake(certificates: [[
        'id' => 99,
        'commonName' => 'www.Beispiel.de',
        'status' => 'ISSUED',
        'issuer' => 'Sectigo',
        'startDate' => '2026-01-10 00:00:00',
        'endDate' => '2027-01-10 00:00:00',
        'san' => ['beispiel.de'],
    ]]);

    app(ImportRegistrarInventory::class)(inwxClient());

    $zertifikat = Certificate::query()->sole();

    expect($zertifikat->common_name)->toBe('www.beispiel.de')
        ->and($zertifikat->provider_reference)->toBe('99')
        ->and($zertifikat->issuer)->toBe('Sectigo')
        ->and($zertifikat->expires_on->toDateString())->toBe('2027-01-10')
        ->and($zertifikat->alternative_names)->toBe(['beispiel.de']);
});

it('legt beim zweiten Lauf keine Dublette an, sondern gleicht ab', function (): void {
    inwxFake(domains: [[
        'roId' => 4711, 'domain' => 'beispiel.de', 'status' => 'OK',
        'exDate' => '2027-03-04 10:00:00', 'ns' => ['ns1.example.net'],
    ]]);

    app(ImportRegistrarInventory::class)(inwxClient());

    inwxFake(domains: [[
        'roId' => 4711, 'domain' => 'beispiel.de', 'status' => 'TRANSFER',
        'exDate' => '2028-03-04 10:00:00', 'ns' => ['ns9.example.net'],
    ]]);

    $ergebnis = app(ImportRegistrarInventory::class)(inwxClient());

    expect($ergebnis['domains'])->toBe(['new' => 0, 'updated' => 1])
        ->and(Domain::query()->count())->toBe(1);

    $domain = Domain::query()->sole();

    expect($domain->status)->toBe('TRANSFER')
        ->and($domain->expires_on->toDateString())->toBe('2028-03-04');
});

it('wirft die von Hand gesetzte Zuordnung beim Abgleich nicht weg', function (): void {
    $kunde = Customer::factory()->create();

    Domain::factory()->create([
        'name' => 'beispiel.de',
        'customer_id' => $kunde->id,
        'expires_on' => '2026-01-01',
    ]);

    inwxFake(domains: [[
        'roId' => 4711, 'domain' => 'beispiel.de', 'status' => 'OK',
        'exDate' => '2029-03-04 10:00:00',
    ]]);

    app(ImportRegistrarInventory::class)(inwxClient());

    $domain = Domain::query()->sole();

    // Der Registrar kennt unsere Kunden nicht — er darf sie nicht überschreiben.
    expect($domain->customer_id)->toBe($kunde->id)
        ->and($domain->expires_on->toDateString())->toBe('2029-03-04');
});

it('schreibt im Trockenlauf nichts', function (): void {
    inwxFake(domains: [[
        'roId' => 4711, 'domain' => 'beispiel.de', 'exDate' => '2027-03-04 10:00:00',
    ]]);

    $ergebnis = app(ImportRegistrarInventory::class)(inwxClient(), dryRun: true);

    expect($ergebnis['domains'])->toBe(['new' => 1, 'updated' => 0])
        ->and(Domain::query()->count())->toBe(0);
});

it('bricht mit der Meldung des Anbieters ab, wenn INWX einen Fehler liefert', function (): void {
    Http::fake(fn (): array => ['code' => 2200, 'msg' => 'Authentication error']);

    app(ImportRegistrarInventory::class)(inwxClient());
})->throws(RegistrarException::class, 'Authentication error');

it('bricht ab, wenn ein Domaineintrag keinen erkennbaren Namen hat', function (): void {
    // So sähe es aus, wenn INWX die Feldnamen änderte.
    inwxFake(domains: [['roId' => 4711, 'unbekanntesFeld' => 'beispiel.de']]);

    app(ImportRegistrarInventory::class)(inwxClient());
})->throws(RegistrarException::class, 'ohne erkennbaren Namen');

it('liest die Zeilenantwort von Domain-Reselling', function (): void {
    Http::fake(fn (Request $anfrage) => Http::response(str_contains((string) $anfrage->body(), 'QueryDomainList')
        ? resellingAntwort([
            'total' => [1],
            'domain' => ['Beispiel.DE'],
            'status' => ['ACTIVE'],
            'creationdate' => ['2021-05-06 12:00:00'],
            'expirationdate' => ['2027-05-06 12:00:00'],
            'renewalmode' => ['AUTORENEW'],
            'nameserver' => ['ns1.example.net ns2.example.net'],
        ])
        : resellingAntwort(['total' => [0]])));

    $client = new DomainResellingClient([
        'endpoint' => 'https://api.example.test/api/call.cgi',
        'username' => 'benutzer',
        'password' => 'geheim',
    ]);

    app(ImportRegistrarInventory::class)($client);

    $domain = Domain::query()->sole();

    expect($domain->name)->toBe('beispiel.de')
        ->and($domain->provider)->toBe(RegistrarProvider::DomainReselling)
        ->and($domain->expires_on->toDateString())->toBe('2027-05-06')
        ->and($domain->auto_renew)->toBeTrue()
        ->and($domain->nameservers)->toBe(['ns1.example.net', 'ns2.example.net']);
});

it('bricht bei Domain-Reselling mit Code und Beschreibung ab', function (): void {
    Http::fake(fn () => Http::response(resellingAntwort([], 530, 'Authorization failed')));

    $client = new DomainResellingClient([
        'endpoint' => 'https://api.example.test/api/call.cgi',
        'username' => 'benutzer',
        'password' => 'falsch',
    ]);

    app(ImportRegistrarInventory::class)($client);
})->throws(RegistrarException::class, 'Authorization failed');

it('uebergeht Zertifikate ohne Kennung des Anbieters, statt Dubletten anzulegen', function (): void {
    inwxFake(certificates: [['commonName' => 'ohne-kennung.de', 'status' => 'ISSUED']]);

    $ergebnis = app(ImportRegistrarInventory::class)(inwxClient());

    expect($ergebnis['skipped'])->toBe(1)
        ->and(Certificate::query()->count())->toBe(0);
});

it('liefert nur eingerichtete Anbieter', function (): void {
    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::Inwx->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    $anbieter = array_map(
        fn ($client) => $client->provider(),
        app(RegistrarClientFactory::class)->configured(),
    );

    expect($anbieter)->toBe([RegistrarProvider::Inwx]);
});

it('liest Zertifikate von Domain-Reselling und verwechselt Produktklasse nicht mit dem Namen', function (): void {
    Http::fake(fn (Request $anfrage) => Http::response(str_contains((string) $anfrage->body(), 'QuerySSLCertList')
        ? resellingAntwort([
            'total' => [1],
            // Die Produktklasse steht in der Antwort und darf den Namen nicht verdrängen.
            'sslcertclass' => ['SSL_CERT_CLASS_SECTIGO_DV'],
            'commonname' => ['www.Beispiel.de'],
            'sslcertid' => ['SSL-4711'],
            'status' => ['ACTIVE'],
            'creationdate' => ['2026-02-01 00:00:00'],
            'expirationdate' => ['2027-02-01 00:00:00'],
            'sslcertsan' => ['beispiel.de shop.beispiel.de'],
        ])
        : resellingAntwort(['total' => [0]])));

    $client = new DomainResellingClient([
        'endpoint' => 'https://api.example.test/api/call.cgi',
        'username' => 'benutzer',
        'password' => 'geheim',
    ]);

    app(ImportRegistrarInventory::class)($client);

    $zertifikat = Certificate::query()->sole();

    expect($zertifikat->common_name)->toBe('www.beispiel.de')
        ->and($zertifikat->provider_reference)->toBe('SSL-4711')
        ->and($zertifikat->issuer)->toBe('SSL_CERT_CLASS_SECTIGO_DV')
        ->and($zertifikat->expires_on->toDateString())->toBe('2027-02-01')
        ->and($zertifikat->alternative_names)->toBe(['beispiel.de', 'shop.beispiel.de']);
});

it('erkennt eine umbenannte Domain an der Kennung des Registrars wieder', function (): void {
    inwxFake(domains: [[
        'roId' => 4711, 'domain' => 'alter-name.de', 'exDate' => '2027-03-04 10:00:00',
    ]]);

    app(ImportRegistrarInventory::class)(inwxClient());

    // Derselbe Eintrag beim Registrar, anderer Name.
    inwxFake(domains: [[
        'roId' => 4711, 'domain' => 'neuer-name.de', 'exDate' => '2027-03-04 10:00:00',
    ]]);

    $ergebnis = app(ImportRegistrarInventory::class)(inwxClient());

    // Ohne den Anker über die Kennung entstünde hier ein zweiter Datensatz.
    expect($ergebnis['domains'])->toBe(['new' => 0, 'updated' => 1])
        ->and(Domain::query()->count())->toBe(1)
        ->and(Domain::query()->sole()->name)->toBe('neuer-name.de');
});

it('findet eine von Hand angelegte Domain ueber den Namen, wenn sie noch keine Kennung hat', function (): void {
    Domain::factory()->create(['name' => 'von-hand.de', 'provider_reference' => null]);

    inwxFake(domains: [[
        'roId' => 9999, 'domain' => 'von-hand.de', 'exDate' => '2027-03-04 10:00:00',
    ]]);

    $ergebnis = app(ImportRegistrarInventory::class)(inwxClient());

    expect($ergebnis['domains'])->toBe(['new' => 0, 'updated' => 1])
        ->and(Domain::query()->sole()->provider_reference)->toBe('9999');
});
