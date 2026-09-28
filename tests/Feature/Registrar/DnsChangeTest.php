<?php

use App\Actions\Registrar\ApplyDnsChange;
use App\Actions\Registrar\PlanDnsChange;
use App\Enums\RegistrarProvider;
use App\Exceptions\ReadOnlyRecordException;
use App\Livewire\Registrar\DomainDnsPanel;
use App\Models\DnsChange;
use App\Models\Domain;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Support\Registrar\RegistrarException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Der einzige schreibende Zugriff dieses Portals.
 *
 * Bis hierher war alles nur lesend, und das war eine Entscheidung mit Grund: ein
 * falsch gesetzter Eintrag schaltet eine Kundenseite oder deren Mailempfang
 * sofort ab. Auf Ansage kommt der Schreibpfad dazu — und mit ihm die Regeln, die
 * ihn erträglich machen:
 *
 * - Er ist je Umgebung abschaltbar und aus, solange niemand ihn einschaltet.
 * - Geändert wird nur nach Plan, und nur mit der Prüfsumme aus genau diesem
 *   Plan. Wer die Zone nicht gesehen hat, ändert sie nicht.
 * - Nach dem Schreiben wird nachgesehen, ob dort steht, was bestellt war.
 * - Jeder Vorgang wird protokolliert, auch ein halb gelungener.
 *
 * Die Feldnamen stammen aus den offiziellen Beschreibungen beider Anbieter
 * (ResellerInterface „CoreAPI", autoDNS `InterNetX/domainrobot-api`).
 */
beforeEach(function (): void {
    $this->benutzer = User::factory()->create();

    config()->set('portal.dns.writes_enabled', true);
    // Ohne Vorhalten prüft jeder Schritt gegen einen frisch gelesenen Stand.
    config()->set('portal.dns.cache_minutes', 0);

    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::ResellerInterface->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);
});

/**
 * Die Zone, wie ResellerInterface sie liefert — mit zwei TXT an derselben
 * Stelle, weil genau das der schwierige Fall ist: ein SPF-Eintrag neben einem
 * Bestätigungs-Token.
 *
 * @param  array<int, array<string, mixed>>  $records
 * @return array<string, mixed>
 */
function riZoneMit(array $records): array
{
    return [
        'state' => 1000,
        'stateName' => 'OK',
        'domain' => 'aguamix.at',
        'soa' => ['mail' => 'hostmaster@aguamix.at', 'serial' => '2026092801', 'ttl' => 3600],
        'records' => array_combine(
            array_map(fn (array $r): string => (string) $r['id'], $records),
            $records,
        ),
        'total' => count($records),
        'vns' => ['hostname' => ['ns1.example.net']],
    ];
}

/**
 * @return array<string, mixed>
 */
function riRecord(int $id, string $name, string $type, string $content, int $ttl = 3600): array
{
    return ['id' => $id, 'name' => $name, 'ttl' => $ttl, 'type' => $type, 'priority' => 0, 'content' => $content];
}

function riDomain(): Domain
{
    return Domain::factory()->create([
        'name' => 'aguamix.at',
        'provider' => RegistrarProvider::ResellerInterface,
    ]);
}

/**
 * Anmeldung plus eine Zone mit SPF und einem Token daneben.
 *
 * @param  array<int, array<string, mixed>>|null  $records
 */
function riFake(?array $records = null, ?array $zweiteZone = null): void
{
    $records ??= [
        riRecord(11, 'aguamix.at', 'TXT', 'v=spf1 +a +mx ip4:80.81.0.158 ~all'),
        riRecord(12, 'aguamix.at', 'TXT', 'google-site-verification=abc'),
        riRecord(13, '_dmarc', 'TXT', 'v=DMARC1; p=none'),
        riRecord(14, 'aguamix.at', 'A', '80.81.0.158'),
    ];

    $zonen = $zweiteZone === null
        ? Http::response(riZoneMit($records))
        : Http::sequence()->push(riZoneMit($records))->push(riZoneMit($zweiteZone))->whenEmpty(Http::response(riZoneMit($zweiteZone)));

    Http::fake([
        '*/reseller/login' => Http::response(
            ['state' => 1000, 'stateName' => 'OK'],
            200,
            ['Set-Cookie' => 'coreSID=sitzung-123; Path=/; HttpOnly'],
        ),
        '*/dns/getZoneDetails' => $zonen,
        '*/dns/createBackup' => Http::response(['state' => 1000, 'stateName' => 'OK']),
        '*/dns/updateRecord' => Http::response(['state' => 1000, 'stateName' => 'OK']),
        '*/dns/createRecord' => Http::response(['state' => 1000, 'stateName' => 'OK']),
        '*/dns/deleteRecord' => Http::response(['state' => 1000, 'stateName' => 'OK']),
    ]);
}

it('aendert nichts, solange der Schalter aus ist', function (): void {
    config()->set('portal.dns.writes_enabled', false);

    $domain = riDomain();
    Http::fake();

    expect(fn () => app(PlanDnsChange::class)($domain, [
        'vorgang' => 'aendern', 'typ' => 'TXT', 'inhalt' => 'v=spf1 -all',
    ]))->toThrow(RegistrarException::class, 'nicht eingeschaltet');

    // Entscheidend: es wurde nicht einmal gelesen.
    Http::assertNothingSent();
});

it('aendert nichts bei einer von Hand gepflegten Domain', function (): void {
    $domain = Domain::factory()->manual()->create(['name' => 'vonhand.de']);
    Http::fake();

    expect(fn () => app(PlanDnsChange::class)($domain, [
        'vorgang' => 'anlegen', 'typ' => 'TXT', 'inhalt' => 'x',
    ]))->toThrow(RegistrarException::class, 'kein Anschluss');

    Http::assertNothingSent();
});

it('plant eine Aenderung und nennt Ist, Soll und Pruefsumme', function (): void {
    riFake();

    $plan = app(PlanDnsChange::class)(riDomain(), [
        'vorgang' => 'aendern',
        'name' => '_dmarc.aguamix.at',
        'typ' => 'txt',
        'inhalt' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@itm-technologies.de',
    ]);

    // Der Name wird relativ zur Zone gemacht, der Typ groß geschrieben.
    expect($plan->recordName)->toBe('_dmarc')
        ->and($plan->recordType)->toBe('TXT')
        ->and($plan->before?->content)->toBe('v=DMARC1; p=none')
        ->and($plan->before?->reference)->toBe('13')
        ->and($plan->after?->content)->toBe('v=DMARC1; p=quarantine; rua=mailto:dmarc@itm-technologies.de')
        ->and($plan->fingerprint)->toHaveLength(64)
        ->and($plan->describe())->toContain('→');

    // Geplant heißt nicht geschrieben.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'Record'));
});

it('verlangt alt_inhalt, wenn an einer Stelle mehrere Eintraege liegen', function (): void {
    riFake();

    /*
     * Genau der Fall aus der Praxis: an der Domain liegen ein SPF-Eintrag und
     * ein Bestätigungs-Token, beide TXT. „Der TXT-Eintrag" ist dann keine
     * Angabe, und sich einen auszusuchen wäre die schlechteste Lösung.
     */
    expect(fn () => app(PlanDnsChange::class)(riDomain(), [
        'vorgang' => 'aendern', 'typ' => 'TXT', 'inhalt' => 'v=spf1 -all',
    ]))->toThrow(RegistrarException::class, 'alt_inhalt');
});

it('trifft mit alt_inhalt genau einen Eintrag', function (): void {
    riFake();

    $plan = app(PlanDnsChange::class)(riDomain(), [
        'vorgang' => 'aendern',
        'typ' => 'TXT',
        // Mit doppeltem Leerzeichen und in Anführungszeichen — abgetippt trifft
        // es denselben Eintrag.
        'alt_inhalt' => '"v=spf1 +a +mx  ip4:80.81.0.158 ~all"',
        'inhalt' => 'v=spf1 +a +mx a:gateway.itm-technologies.de ip4:80.81.0.158 ~all',
    ]);

    expect($plan->before?->reference)->toBe('11')
        ->and($plan->siblings)->toHaveCount(2);
});

it('legt keinen zweiten gleichen Eintrag an', function (): void {
    riFake();

    // Zwei SPF-Einträge machen beide ungültig.
    expect(fn () => app(PlanDnsChange::class)(riDomain(), [
        'vorgang' => 'anlegen',
        'typ' => 'TXT',
        'inhalt' => 'v=spf1 +a +mx ip4:80.81.0.158 ~all',
    ]))->toThrow(RegistrarException::class, 'schon genau so');
});

it('lehnt eine Aenderung ab, die nichts aendert', function (): void {
    riFake();

    expect(fn () => app(PlanDnsChange::class)(riDomain(), [
        'vorgang' => 'aendern',
        'name' => '_dmarc',
        'typ' => 'TXT',
        'inhalt' => 'v=DMARC1; p=none',
    ]))->toThrow(RegistrarException::class, 'steht schon so da');
});

it('lehnt eine Aenderung ab, wenn es den Eintrag nicht gibt', function (): void {
    riFake();

    expect(fn () => app(PlanDnsChange::class)(riDomain(), [
        'vorgang' => 'aendern',
        'name' => 'mail._domainkey',
        'typ' => 'TXT',
        'inhalt' => 'v=DKIM1; k=rsa; p=AAAA',
    ]))->toThrow(RegistrarException::class, 'keinen TXT-Eintrag');
});

it('schreibt eine Aenderung ueber die Kennung und protokolliert sie', function (): void {
    riFake();

    $domain = riDomain();

    $eingabe = [
        'vorgang' => 'aendern',
        'name' => '_dmarc',
        'typ' => 'TXT',
        'inhalt' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@itm-technologies.de',
    ];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);

    $this->actingAs($this->benutzer);

    $protokoll = app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint);

    // Der Aufruf trifft den Eintrag über seine Kennung, nicht über einen
    // Vergleich der Felder.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'dns/updateRecord')
        && $request['id'] === '13'
        && $request['type'] === 'TXT'
        && $request['content'] === 'v=DMARC1; p=quarantine; rua=mailto:dmarc@itm-technologies.de');

    // Vorher ein Backup — das Netz, das die Beschreibung des Anbieters anbietet.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'dns/createBackup'));

    expect($protokoll->operation)->toBe('aendern')
        ->and($protokoll->record_name)->toBe('_dmarc')
        ->and($protokoll->before)->toContain('v=DMARC1; p=none')
        ->and($protokoll->after)->toContain('p=quarantine')
        ->and($protokoll->user_id)->toBe($this->benutzer->id)
        ->and($protokoll->domain_name)->toBe('aguamix.at');
});

it('schreibt nichts, wenn die Pruefsumme nicht passt', function (): void {
    riFake();

    $domain = riDomain();

    expect(fn () => app(ApplyDnsChange::class)($domain, [
        'vorgang' => 'aendern',
        'name' => '_dmarc',
        'typ' => 'TXT',
        'inhalt' => 'v=DMARC1; p=reject',
    ], str_repeat('0', 64)))->toThrow(RegistrarException::class, 'Prüfsumme passt nicht');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'updateRecord'));
    expect(DnsChange::query()->count())->toBe(0);
});

it('schreibt nichts, wenn sich die Zone zwischen Plan und Anwenden geaendert hat', function (): void {
    $vorher = [riRecord(13, '_dmarc', 'TXT', 'v=DMARC1; p=none')];
    // Jemand anders hat den Eintrag inzwischen angefasst.
    $nachher = [riRecord(13, '_dmarc', 'TXT', 'v=DMARC1; p=reject')];

    riFake($vorher, $nachher);

    $domain = riDomain();

    $eingabe = ['vorgang' => 'aendern', 'name' => '_dmarc', 'typ' => 'TXT', 'inhalt' => 'v=DMARC1; p=quarantine'];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);

    expect(fn () => app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint))
        ->toThrow(RegistrarException::class, 'hat sich etwas geändert');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'updateRecord'));
});

it('legt einen neuen Eintrag ueber createRecord an', function (): void {
    riFake();

    $domain = riDomain();

    $eingabe = [
        'vorgang' => 'anlegen',
        'name' => 'mail._domainkey',
        'typ' => 'TXT',
        'inhalt' => 'v=DKIM1; k=rsa; p=MIIBIjANBg',
        'ttl' => 3600,
    ];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);
    app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'dns/createRecord')
        && $request['name'] === 'mail._domainkey'
        && $request['content'] === 'v=DKIM1; k=rsa; p=MIIBIjANBg');
});

it('loescht einen Eintrag ueber deleteRecord', function (): void {
    riFake();

    $domain = riDomain();

    $eingabe = ['vorgang' => 'loeschen', 'name' => '_dmarc', 'typ' => 'TXT'];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);
    $protokoll = app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'dns/deleteRecord')
        && $request['id'] === '13');

    expect($protokoll->after)->toBeNull();
});

it('merkt, wenn der Eintrag danach nicht in der Zone steht', function (): void {
    /*
     * Der gefährliche Fall: der Anbieter nimmt den Aufruf an, die Zone sieht
     * danach aber anders aus als bestellt. Ohne Nachsehen stünde im Protokoll
     * ein Erfolg.
     */
    $stand = [riRecord(13, '_dmarc', 'TXT', 'v=DMARC1; p=none')];

    riFake($stand, $stand);

    $domain = riDomain();

    $eingabe = ['vorgang' => 'aendern', 'name' => '_dmarc', 'typ' => 'TXT', 'inhalt' => 'v=DMARC1; p=quarantine'];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);
    $protokoll = app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint);

    expect($protokoll->verified)->toBeFalse()
        ->and($protokoll->note)->toContain('steht nach der Änderung nicht in der Zone')
        ->and($protokoll->note)->toContain('noch in der Zone');
});

it('haelt das Protokoll unveraenderlich', function (): void {
    $protokoll = DnsChange::query()->create([
        'domain_name' => 'aguamix.at',
        'provider' => RegistrarProvider::ResellerInterface,
        'operation' => 'aendern',
        'record_name' => '_dmarc',
        'record_type' => 'TXT',
        'fingerprint' => str_repeat('a', 64),
        'applied_at' => now(),
    ]);

    expect(fn () => $protokoll->update(['record_name' => 'anders']))
        ->toThrow(ReadOnlyRecordException::class)
        ->and(fn () => $protokoll->delete())
        ->toThrow(ReadOnlyRecordException::class);
});

/**
 * Eine Zone, wie autoDNS sie liefert. Der verwaltende Nameserver steht in der
 * Zone selbst (`virtualNameServer`) — ohne ihn gibt es den Pfad für eine
 * Änderung nicht.
 *
 * @param  array<int, array<string, mixed>>  $records
 * @return array<string, mixed>
 */
function autodnsZoneAntwort(array $records, ?string $vns = 'ns1.autodns.de'): array
{
    $zone = [
        'origin' => 'aguamix.at',
        'soa' => ['ttl' => 3600, 'email' => 'hostmaster@aguamix.at'],
        'nameServers' => [['name' => 'ns1.autodns.de']],
        'resourceRecords' => $records,
    ];

    if ($vns !== null) {
        $zone['virtualNameServer'] = $vns;
    }

    return [
        'stid' => '20260928-app1',
        'status' => ['code' => 'S0205', 'type' => 'SUCCESS', 'text' => 'Zone gelesen.'],
        'data' => [$zone],
    ];
}

function autodnsDomain(): Domain
{
    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim', 'context' => '4'],
    ]);

    return Domain::factory()->create([
        'name' => 'aguamix.at',
        'provider' => RegistrarProvider::AutoDns,
    ]);
}

it('aendert bei autoDNS einen Eintrag in einem einzigen PATCH', function (): void {
    $records = [
        ['name' => '_dmarc', 'type' => 'TXT', 'value' => 'v=DMARC1; p=none', 'ttl' => 3600],
    ];

    Http::fake([
        '*' => Http::response(autodnsZoneAntwort($records)),
    ]);

    $domain = autodnsDomain();

    $eingabe = ['vorgang' => 'aendern', 'name' => '_dmarc', 'typ' => 'TXT', 'inhalt' => 'v=DMARC1; p=quarantine'];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);

    expect($plan->zone->nameServer)->toBe('ns1.autodns.de');

    app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint);

    /*
     * Entfernen und Anlegen zusammen in einem Aufruf — und als PATCH, nicht als
     * PUT: PUT schreibt die ganze Zone neu.
     */
    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        return str_contains($request->url(), 'zone/aguamix.at/ns1.autodns.de')
            && $request['resourceRecordsRem'][0]['value'] === 'v=DMARC1; p=none'
            && $request['resourceRecordsAdd'][0]['value'] === 'v=DMARC1; p=quarantine'
            && $request['resourceRecordsAdd'][0]['name'] === '_dmarc';
    });
});

it('laesst beim Ursprung den Namen weg, wie autoDNS ihn selbst liefert', function (): void {
    // Die Leseseite macht aus einem fehlenden Namen `@`; beim Schreiben geht
    // derselbe Weg zurück, damit `resourceRecordsRem` den Eintrag trifft.
    $records = [
        ['type' => 'TXT', 'value' => 'v=spf1 ~all', 'ttl' => 3600],
    ];

    Http::fake(['*' => Http::response(autodnsZoneAntwort($records))]);

    $domain = autodnsDomain();

    $eingabe = ['vorgang' => 'aendern', 'typ' => 'TXT', 'inhalt' => 'v=spf1 -all'];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);

    expect($plan->recordName)->toBe('@');

    app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && ! array_key_exists('name', $request['resourceRecordsAdd'][0])
        && ! array_key_exists('name', $request['resourceRecordsRem'][0]));
});

it('bricht bei autoDNS ab, wenn kein verwaltender Nameserver bekannt ist', function (): void {
    $records = [['name' => '_dmarc', 'type' => 'TXT', 'value' => 'v=DMARC1; p=none']];

    Http::fake(['*' => Http::response(autodnsZoneAntwort($records, vns: null))]);

    $domain = autodnsDomain();

    $eingabe = ['vorgang' => 'aendern', 'name' => '_dmarc', 'typ' => 'TXT', 'inhalt' => 'v=DMARC1; p=quarantine'];

    $plan = app(PlanDnsChange::class)($domain, $eingabe);

    expect(fn () => app(ApplyDnsChange::class)($domain, $eingabe, $plan->fingerprint))
        ->toThrow(RegistrarException::class, 'keinen verwaltenden Nameserver');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');
});

it('zeigt die Aenderungen auf der Detailseite, auch wenn die Zone nicht lesbar ist', function (): void {
    $domain = riDomain();

    DnsChange::query()->create([
        'domain_id' => $domain->getKey(),
        'domain_name' => $domain->name,
        'provider' => RegistrarProvider::ResellerInterface,
        'operation' => 'aendern',
        'record_name' => '_dmarc',
        'record_type' => 'TXT',
        'before' => '_dmarc TXT v=DMARC1; p=none',
        'after' => '_dmarc TXT v=DMARC1; p=quarantine',
        'fingerprint' => str_repeat('b', 64),
        'verified' => false,
        'note' => 'Der neue Eintrag steht nach der Änderung nicht in der Zone.',
        'user_id' => $this->benutzer->getKey(),
        'applied_at' => now(),
    ]);

    /*
     * Der Anbieter antwortet nicht. Das Protokoll muss trotzdem dastehen: nach
     * einer Störung im Mailempfang ist „wer hat wann was gesetzt" die erste
     * Frage, und die Antwort darf nicht am Anbieter hängen.
     */
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::actingAs($this->benutzer)
        ->test(DomainDnsPanel::class, ['domain' => $domain])
        ->assertSee('Aus dem Portal geändert')
        ->assertSee('_dmarc')
        ->assertSee($this->benutzer->name)
        // Der Anbieter hat den Aufruf angenommen, die Zone zeigte danach etwas
        // anderes — das muss auffallen.
        ->assertSee('nicht bestätigt');
});
