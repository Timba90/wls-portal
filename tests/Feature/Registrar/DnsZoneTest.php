<?php

use App\Actions\Registrar\ReadDnsZone;
use App\Enums\RegistrarProvider;
use App\Livewire\Registrar\DomainDnsPanel;
use App\Models\Domain;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Support\Registrar\AutoDnsClient;
use App\Support\Registrar\RegistrarClient;
use App\Support\Registrar\RegistrarClientFactory;
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
 * Setzt einen Anschluss ein, der keine Zone lesen kann.
 *
 * Beide echten Anschluesse koennen es inzwischen. Die Schnittstelle sieht den
 * Fall weiter vor — ein kuenftiger Anbieter ohne Lesezugriff —, und die
 * Oberflaeche muss ihn beherrschen: „kann dieser Anbieter nicht" ist ein
 * Zustand, kein Fehlschlag. Geprueft wird er darum an einer Attrappe.
 */
function anschlussOhneZone(): void
{
    $anschluss = Mockery::mock(RegistrarClient::class);
    $anschluss->shouldReceive('canReadZone')->andReturnFalse();

    $attrappe = Mockery::mock(RegistrarClientFactory::class);
    $attrappe->shouldReceive('for')->andReturn($anschluss);

    app()->instance(RegistrarClientFactory::class, $attrappe);
}

function riAnschluss(array $ueberschreibungen = []): ResellerInterfaceClient
{
    return new ResellerInterfaceClient(array_merge([
        'endpoint' => 'https://core.resellerinterface.de',
        'branch' => 'stable',
        'username' => 'benutzer',
        'password' => 'geheim',
    ], $ueberschreibungen));
}

/**
 * Eine Zone, wie ResellerInterface sie liefert.
 *
 * Form und Feldnamen stammen aus der OpenAPI-Beschreibung des Anbieters
 * („CoreAPI - Reseller-Interface", `dns/getZoneDetails`): `records` ist dort
 * keine Liste, sondern eine Zuordnung `Record-ID => Eintrag`, der Ursprung
 * steht als voller Domainname, und der Nameserver-Satz liegt unter `vns`.
 */
function riZone(): array
{
    return [
        'state' => 1000,
        'stateName' => 'OK',
        'domainID' => 4711,
        'zoneID' => 12345,
        'domain' => 'beispiel.de',
        'soa' => [
            'active' => true,
            'primary' => 'ns1.example.net',
            'mail' => 'hostmaster@beispiel.de',
            'serial' => '2026092886',
            'refresh' => 10800,
            'ttl' => 86400,
        ],
        'records' => [
            '15666749' => ['id' => 15666749, 'name' => 'beispiel.de', 'ttl' => 86400, 'type' => 'A', 'priority' => 0, 'content' => '203.0.113.10'],
            '15666750' => ['id' => 15666750, 'name' => 'www', 'ttl' => 3600, 'type' => 'CNAME', 'priority' => 0, 'content' => 'beispiel.de.'],
            '15666751' => ['id' => 15666751, 'name' => 'beispiel.de', 'ttl' => 86400, 'type' => 'MX', 'priority' => 10, 'content' => 'mail.beispiel.de.'],
            '15666752' => ['id' => 15666752, 'name' => 'alt.beispiel.de', 'ttl' => 86400, 'type' => 'TXT', 'priority' => 0, 'content' => 'v=spf1 -all'],
            '15666753' => ['id' => 15666753, 'name' => 'ziel', 'ttl' => 86400, 'type' => 'HEADER', 'priority' => 0, 'content' => '', 'data' => ['uri' => 'beispiel.de', 'redirectCode' => '301']],
        ],
        'total' => 5,
        'vns' => ['vnsID' => 12345, 'soaMail' => 'info@example.org', 'hostname' => ['ns1.example.net', 'ns2.example.net']],
    ];
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

describe('ResellerInterface', function (): void {
    /*
     * Lange konnte dieser Anschluss keine Zone lesen: von `dns/*` waren nur
     * schreibende Funktionen belegt, und der lesende Name wurde nicht geraten —
     * Fehlversuche bei genau diesem Anbieter haben das Konto schon einmal
     * gesperrt. Die OpenAPI-Beschreibung des Anbieters nennt ihn:
     * `dns/getZoneDetails` („Details zu einer Zone anzeigen") mit dem Recht
     * „Zonen einsehen" (api.dns.view). Damit steht er fest.
     */
    beforeEach(function (): void {
        Http::fake([
            '*/reseller/login' => Http::response(
                ['state' => 1000, 'stateName' => 'OK'],
                200,
                ['Set-Cookie' => 'coreSID=sitzung-123; Path=/; HttpOnly'],
            ),
        ]);
    });

    it('liest die Zone ueber dns/getZoneDetails', function (): void {
        Http::fake(['*/dns/getZoneDetails' => Http::response(riZone())]);

        $zone = riAnschluss()->zone('Beispiel.DE');

        expect($zone->origin)->toBe('beispiel.de')
            ->and($zone->ttl)->toBe(86400)
            ->and($zone->soaEmail)->toBe('hostmaster@beispiel.de')
            // Ein Änderungsdatum nennt der Anbieter nicht; die Seriennummer
            // `2026092886` trägt es im Muster JJJJMMTT.
            ->and($zone->updatedAt?->toDateString())->toBe('2026-09-28')
            // Vorrang hat der virtuelle Nameserver-Satz des Anbieters.
            ->and($zone->nameservers)->toBe(['ns1.example.net', 'ns2.example.net'])
            ->and($zone->records)->toHaveCount(5);

        $records = collect($zone->sortedRecords())->keyBy(
            fn ($record): string => $record->type.':'.$record->name,
        );

        // Der Ursprung steht beim Anbieter als voller Domainname und wird zu
        // `@` — so wie bei jedem anderen Anschluss auch.
        expect($records['A:@']->content)->toBe('203.0.113.10')
            ->and($records['A:@']->ttl)->toBe(86400)
            // Priorität nur, wo sie eine Bedeutung hat.
            ->and($records['A:@']->priority)->toBeNull()
            ->and($records['MX:@']->priority)->toBe(10)
            ->and($records['CNAME:www']->content)->toBe('beispiel.de.')
            // Ein voll ausgeschriebener Untername wird auf denselben Stand
            // gebracht.
            ->and($records->has('TXT:alt'))->toBeTrue()
            // Ein Spezial-Record trägt kein `content`, sondern `data`.
            ->and($records['HEADER:ziel']->content)->toBe('uri=beispiel.de redirectCode=301');
    });

    it('schickt dafuer nur den Domainnamen', function (): void {
        Http::fake(['*/dns/getZoneDetails' => Http::response(riZone())]);

        riAnschluss()->zone('beispiel.de');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'dns/getZoneDetails')) {
                return true;
            }

            // Nur der Name — kein Feld, das etwas ändern könnte.
            return $request->data() === ['domain' => 'beispiel.de'];
        });
    });

    it('nimmt die NS-Eintraege der Zone, wenn kein virtueller Nameserver genannt ist', function (): void {
        $antwort = riZone();
        unset($antwort['vns']);
        $antwort['records']['15666754'] = [
            'id' => 15666754, 'name' => 'beispiel.de', 'ttl' => 86400,
            'type' => 'NS', 'priority' => 0, 'content' => 'ns3.example.net.',
        ];

        Http::fake(['*/dns/getZoneDetails' => Http::response($antwort)]);

        expect(riAnschluss()->zone('beispiel.de')->nameservers)->toBe(['ns3.example.net']);
    });

    it('liefert kein Aenderungsdatum, wenn die Seriennummer keins enthaelt', function (): void {
        $antwort = riZone();
        // Nicht jede Zone zählt ihre Seriennummer nach Datum; ein erfundenes
        // Datum wäre schlechter als keins.
        $antwort['soa']['serial'] = '42';

        Http::fake(['*/dns/getZoneDetails' => Http::response($antwort)]);

        expect(riAnschluss()->zone('beispiel.de')->updatedAt)->toBeNull();
    });

    it('bricht ab, wenn die Antwort keine Eintraege enthaelt', function (): void {
        $antwort = riZone();
        unset($antwort['records']);

        Http::fake(['*/dns/getZoneDetails' => Http::response($antwort)]);

        // Eine leere Zone anzuzeigen, wo der Anbieter etwas anderes gemeint
        // hat, hiesse: ein Eintrag sei verschwunden.
        expect(fn () => riAnschluss()->zone('beispiel.de'))
            ->toThrow(RegistrarException::class, 'keine Eintragsliste');
    });

    it('verlangt einen Domainnamen und ruft dafuer nichts auf', function (): void {
        expect(fn () => riAnschluss()->zone('  '))
            ->toThrow(RegistrarException::class, 'Ohne Domainnamen');

        Http::assertNothingSent();
    });
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

        anschlussOhneZone();

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

        anschlussOhneZone();

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
