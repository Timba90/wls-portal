<?php

use App\Actions\Registrar\ReadDnsZone;
use App\Enums\RegistrarProvider;
use App\Livewire\Registrar\DomainDnsPanel;
use App\Models\Domain;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Support\Registrar\AutoDnsClient;
use App\Support\Registrar\RegistrarException;
use App\Support\Registrar\ResellerInterfaceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Die Zone einer Domain wird beim Anbieter gelesen — nur gelesen.
 *
 * Umschlag und Feldnamen stammen aus der offiziellen OpenAPI-Beschreibung von
 * autoDNS (InterNetX/domainrobot-api, `src/domainrobot.json`): `GET
 * zone/{name}` heisst dort „Zone Info" (0205), die Eintraege liegen unter
 * `resourceRecords` mit `name`, `type`, `value`, `ttl` und `pref`, die
 * Haupt-IP getrennt unter `main`. Geraten ist hier nichts.
 */
function zonenAntwort(array $zone, string $typ = 'SUCCESS', string $text = 'Zone gelesen.'): array
{
    return [
        'stid' => '20260928-app1-dev',
        'status' => ['code' => 'S0205', 'type' => $typ, 'text' => $text],
        'object' => ['type' => 'Zone', 'value' => $zone['origin'] ?? 'beispiel.de'],
        'data' => [$zone],
    ];
}

function autodnsAnschluss(array $ueberschreibungen = []): AutoDnsClient
{
    return new AutoDnsClient(array_merge([
        'endpoint' => 'https://api.autodns.com/v1/',
        'username' => 'benutzer',
        'password' => 'geheim',
        'context' => '4',
    ], $ueberschreibungen));
}

/**
 * Eine vollstaendige Zone, wie der Anbieter sie liefert.
 */
function beispielZone(array $ueberschreibungen = []): array
{
    return array_merge([
        'origin' => 'beispiel.de',
        'updated' => '2026-09-20T11:30:00.000+0200',
        'soa' => ['ttl' => 86400, 'email' => 'hostmaster@beispiel.de', 'refresh' => 43200],
        'nameServers' => [['name' => 'ns1.example.net'], ['name' => 'ns2.example.net']],
        'resourceRecords' => [
            ['name' => 'www', 'type' => 'CNAME', 'value' => 'beispiel.de.', 'ttl' => 3600],
            ['name' => '@', 'type' => 'MX', 'value' => 'mail.beispiel.de.', 'ttl' => 7200, 'pref' => 10],
            ['name' => '@', 'type' => 'TXT', 'value' => 'v=spf1 include:example.net -all'],
        ],
    ], $ueberschreibungen);
}

it('liest eine Zone und ordnet jedes Feld zu', function (): void {
    Http::fake(['*/zone/beispiel.de' => Http::response(zonenAntwort(beispielZone()))]);

    $zone = autodnsAnschluss()->zone('beispiel.de');

    expect($zone->origin)->toBe('beispiel.de')
        ->and($zone->ttl)->toBe(86400)
        ->and($zone->soaEmail)->toBe('hostmaster@beispiel.de')
        ->and($zone->updatedAt?->format('Y-m-d H:i'))->toBe('2026-09-20 11:30')
        ->and($zone->nameservers)->toBe(['ns1.example.net', 'ns2.example.net'])
        ->and($zone->records)->toHaveCount(3);

    $mx = collect($zone->records)->firstWhere('type', 'MX');

    expect($mx->name)->toBe('@')
        ->and($mx->content)->toBe('mail.beispiel.de.')
        ->and($mx->ttl)->toBe(7200)
        ->and($mx->priority)->toBe(10)
        ->and($mx->derived)->toBeFalse();

    // Ein TXT-Eintrag ohne TTL bleibt ohne TTL und wird nicht auf 0 gesetzt.
    expect(collect($zone->records)->firstWhere('type', 'TXT')->ttl)->toBeNull();
});

it('macht die getrennt gefuehrte Haupt-IP als Eintrag sichtbar', function (): void {
    Http::fake(['*/zone/beispiel.de' => Http::response(zonenAntwort(beispielZone([
        'main' => ['address' => '203.0.113.10', 'ttl' => 1800],
        'wwwInclude' => true,
        'resourceRecords' => [],
    ])))]);

    $zone = autodnsAnschluss()->zone('beispiel.de');

    expect($zone->records)->toHaveCount(2);

    $ursprung = collect($zone->records)->firstWhere('name', '@');

    expect($ursprung->type)->toBe('A')
        ->and($ursprung->content)->toBe('203.0.113.10')
        ->and($ursprung->ttl)->toBe(1800)
        // Gekennzeichnet, weil er in der Eintragsliste des Anbieters nicht
        // steht: er entsteht aus der Haupt-IP der Zone.
        ->and($ursprung->derived)->toBeTrue()
        ->and(collect($zone->records)->firstWhere('name', 'www')->content)->toBe('203.0.113.10');
});

it('erzeugt keinen www-Eintrag, wenn der Anbieter ihn nicht erzeugt', function (): void {
    Http::fake(['*/zone/beispiel.de' => Http::response(zonenAntwort(beispielZone([
        'main' => ['address' => '203.0.113.10'],
        'wwwInclude' => false,
        'resourceRecords' => [],
    ])))]);

    expect(autodnsAnschluss()->zone('beispiel.de')->records)->toHaveCount(1);
});

it('erkennt eine IPv6-Haupt-IP als AAAA', function (): void {
    Http::fake(['*/zone/beispiel.de' => Http::response(zonenAntwort(beispielZone([
        'main' => ['address' => '2001:db8::1'],
        'resourceRecords' => [],
    ])))]);

    expect(autodnsAnschluss()->zone('beispiel.de')->records[0]->type)->toBe('AAAA');
});

it('liest ohne Einstellung ueber zone/{name} und mit ihr ueber den Nameserver', function (): void {
    Http::fake(['*' => Http::response(zonenAntwort(beispielZone()))]);

    autodnsAnschluss()->zone('beispiel.de');
    autodnsAnschluss(['name_server' => 'ns1.example.net'])->zone('beispiel.de');

    Http::assertSent(fn (Request $anfrage): bool => str_ends_with($anfrage->url(), '/zone/beispiel.de'));
    Http::assertSent(fn (Request $anfrage): bool => str_ends_with($anfrage->url(), '/zone/beispiel.de/ns1.example.net'));
});

it('gibt die Meldung des Anbieters weiter, statt sie zu verschlucken', function (): void {
    Http::fake(['*/zone/beispiel.de' => Http::response(
        zonenAntwort(beispielZone(), typ: 'ERROR', text: 'Zone nicht gefunden.'),
    )]);

    expect(fn () => autodnsAnschluss()->zone('beispiel.de'))
        ->toThrow(RegistrarException::class, 'Zone nicht gefunden.');
});

it('verlangt einen Domainnamen', function (): void {
    expect(fn () => autodnsAnschluss()->zone('  '))
        ->toThrow(RegistrarException::class, 'Ohne Domainnamen');

    Http::assertNothingSent();
});

it('liest keine Zone bei ResellerInterface und raet den Aufruf nicht', function (): void {
    $anschluss = new ResellerInterfaceClient([
        'endpoint' => 'https://core.resellerinterface.de',
        'branch' => 'stable',
        'username' => 'benutzer',
        'password' => 'geheim',
    ]);

    expect($anschluss->canReadZone())->toBeFalse()
        ->and(fn () => $anschluss->zone('beispiel.de'))
        ->toThrow(RegistrarException::class, 'nicht dokumentiert und wird nicht geraten');

    // Entscheidend: es wurde nichts versucht. Fehlversuche bei genau diesem
    // Anbieter haben das Konto schon einmal gesperrt.
    Http::assertNothingSent();
});

describe('ReadDnsZone', function (): void {
    beforeEach(function (): void {
        IntegrationCredential::query()->create([
            'provider' => RegistrarProvider::AutoDns->value,
            'credentials' => ['username' => 'benutzer', 'password' => 'geheim', 'context' => '4'],
        ]);
    });

    it('haelt den gelesenen Stand vor und liest auf Verlangen neu', function (): void {
        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        Http::fake(['*' => Http::response(zonenAntwort(beispielZone()))]);

        $lesen = app(ReadDnsZone::class);

        $lesen($domain);
        $lesen($domain);

        Http::assertSentCount(1);

        $lesen($domain, force: true);

        Http::assertSentCount(2);
    });

    it('haelt eine Stoerung nicht vor', function (): void {
        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        $gestoert = true;

        Http::fake(function () use (&$gestoert) {
            return $gestoert
                ? Http::response(zonenAntwort(beispielZone(), typ: 'ERROR', text: 'Kurz gestört.'))
                : Http::response(zonenAntwort(beispielZone()));
        });

        $lesen = app(ReadDnsZone::class);

        expect(fn () => $lesen($domain))->toThrow(RegistrarException::class);

        $gestoert = false;

        // Ohne diese Zusicherung koennte ein einzelner Aussetzer beim Anbieter
        // fuer die volle Dauer des Vorhaltens Bestand haben.
        expect($lesen($domain)->origin)->toBe('beispiel.de');
    });

    it('trennt die Staende zweier Anbieter', function (): void {
        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        $vorher = app(ReadDnsZone::class)->cacheKey($domain);

        // Dieselbe Domain, anderer Anschluss: der alte Stand ist dann nicht
        // mehr gemeint. Zwei Datensaetze gleichen Namens gibt es nicht —
        // `domains.name` ist eindeutig.
        $domain->provider = RegistrarProvider::ResellerInterface;

        expect(app(ReadDnsZone::class)->cacheKey($domain))->not->toBe($vorher);
    });

    it('uebersteht das Ablegen im Zwischenspeicher unverfaelscht', function (): void {
        Http::fake(['*' => Http::response(zonenAntwort(beispielZone([
            'main' => ['address' => '203.0.113.10', 'ttl' => 1800],
            'wwwInclude' => true,
        ])))]);

        $zone = autodnsAnschluss()->zone('beispiel.de');

        /*
         * Die Tests laufen mit dem Array-Speicher, der nichts serialisiert —
         * Redis tut es. Ohne diese Zusicherung wuerde ein Wertobjekt, das sich
         * nicht serialisieren laesst, erst im Betrieb auffallen.
         */
        $wieder = unserialize(serialize($zone));

        expect($wieder->origin)->toBe($zone->origin)
            ->and($wieder->ttl)->toBe($zone->ttl)
            ->and($wieder->soaEmail)->toBe($zone->soaEmail)
            ->and($wieder->updatedAt?->toIso8601String())->toBe($zone->updatedAt?->toIso8601String())
            ->and($wieder->nameservers)->toBe($zone->nameservers)
            ->and(count($wieder->records))->toBe(count($zone->records))
            ->and($wieder->records[0]->type)->toBe($zone->records[0]->type)
            ->and($wieder->records[0]->content)->toBe($zone->records[0]->content)
            ->and(collect($wieder->records)->firstWhere('derived', true)->content)->toBe('203.0.113.10');
    });

    it('bricht ohne Zugangsdaten ab, ohne den Anbieter anzusprechen', function (): void {
        IntegrationCredential::query()->delete();
        Cache::flush();

        $domain = Domain::factory()->create(['provider' => RegistrarProvider::AutoDns]);

        Http::fake();

        expect(fn () => app(ReadDnsZone::class)($domain))
            ->toThrow(RegistrarException::class, 'keine Zugangsdaten hinterlegt');

        Http::assertNothingSent();
    });

    it('bricht bei einem Anbieter ohne Lesezugriff ab', function (): void {
        $domain = Domain::factory()->create(['provider' => RegistrarProvider::ResellerInterface]);

        Http::fake();

        expect(fn () => app(ReadDnsZone::class)($domain))
            ->toThrow(RegistrarException::class, 'kann keine DNS-Zone lesen');

        Http::assertNothingSent();
    });
});

describe('Anzeige', function (): void {
    beforeEach(function (): void {
        $this->benutzer = User::factory()->create();

        IntegrationCredential::query()->create([
            'provider' => RegistrarProvider::AutoDns->value,
            'credentials' => ['username' => 'benutzer', 'password' => 'geheim', 'context' => '4'],
        ]);
    });

    it('zeigt die Eintraege der Zone', function (): void {
        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        Http::fake(['*' => Http::response(zonenAntwort(beispielZone()))]);

        Livewire::actingAs($this->benutzer)
            ->test(DomainDnsPanel::class, ['domain' => $domain])
            ->assertSee('mail.beispiel.de.')
            ->assertSee('v=spf1 include:example.net -all')
            ->assertSee('hostmaster@beispiel.de')
            ->assertSee('CNAME');
    });

    it('schraenkt auf einen Typ ein', function (): void {
        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        Http::fake(['*' => Http::response(zonenAntwort(beispielZone()))]);

        Livewire::actingAs($this->benutzer)
            ->test(DomainDnsPanel::class, ['domain' => $domain])
            ->set('filterType', 'MX')
            ->assertSee('mail.beispiel.de.')
            ->assertDontSee('v=spf1 include:example.net -all');
    });

    it('sagt bei einem Anbieter ohne Lesezugriff, dass er es nicht kann', function (): void {
        $domain = Domain::factory()->create(['provider' => RegistrarProvider::ResellerInterface]);

        Http::fake();

        Livewire::actingAs($this->benutzer)
            ->test(DomainDnsPanel::class, ['domain' => $domain])
            ->assertSee('liefert hier noch keine DNS-Zone')
            ->assertDontSee('Neu laden');

        Http::assertNothingSent();
    });

    it('nennt fehlende Zugangsdaten und ruft dafuer nichts auf', function (): void {
        IntegrationCredential::query()->delete();

        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        Http::fake();

        /*
         * Genau dieser Wortlaut steht im Browser-Rundgang
         * (`tests/Browser/SmokeTest.php`). Dort laesst er sich nicht mit einer
         * Attrappe absichern — hier schon, und damit faellt eine Aenderung am
         * Text hier auf und nicht erst im Rundgang.
         */
        Livewire::actingAs($this->benutzer)
            ->test(DomainDnsPanel::class, ['domain' => $domain])
            ->assertSee('Die Zone konnte nicht gelesen werden.')
            ->assertSee('keine Zugangsdaten hinterlegt');

        Http::assertNothingSent();
    });

    it('zeigt die Meldung des Anbieters, wenn das Lesen scheitert', function (): void {
        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        Http::fake(['*' => Http::response(
            zonenAntwort(beispielZone(), typ: 'ERROR', text: 'Zone nicht gefunden.'),
        )]);

        Livewire::actingAs($this->benutzer)
            ->test(DomainDnsPanel::class, ['domain' => $domain])
            ->assertSee('Die Zone konnte nicht gelesen werden.')
            ->assertSee('Zone nicht gefunden.');
    });

    it('bietet den Reiter auf der Detailseite an', function (): void {
        $domain = Domain::factory()->create(['name' => 'beispiel.de', 'provider' => RegistrarProvider::AutoDns]);

        Http::fake(['*' => Http::response(zonenAntwort(beispielZone()))]);

        $this->actingAs($this->benutzer)
            ->get(route('domains.show', $domain).'?bereich=dns')
            ->assertOk()
            ->assertSee('DNS-Zone')
            ->assertSee('mail.beispiel.de.');
    });
});
