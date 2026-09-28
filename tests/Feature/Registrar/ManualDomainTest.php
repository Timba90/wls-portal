<?php

use App\Actions\Registrar\CreateManualDomain;
use App\Actions\Registrar\ImportRegistrarInventory;
use App\Actions\Registrar\UpdateManualDomain;
use App\Enums\RegistrarProvider;
use App\Exceptions\ReadOnlyRecordException;
use App\Livewire\Registrar\DomainForm;
use App\Livewire\System\IntegrationSettings;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\User;
use App\Support\Registrar\DnsZone;
use App\Support\Registrar\RegistrarClient;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use App\Support\Registrar\RemoteDomain;
use Livewire\Livewire;

/**
 * Domains, deren Registrar hier keine Schnittstelle hat.
 *
 * §60: „Domains anderer Provider müssen ebenfalls manuell verwaltbar sein."
 * Ohne diesen Weg fehlten sie im Bestand ganz — obwohl ihr Ablaufdatum genauso
 * zählt und der Kunde dieselbe Rechnung bekommt.
 *
 * Die Grenze ist dabei das Eigentliche: der technische Stand einer
 * *importierten* Domain bleibt unberührbar. Er kommt vom Anbieter, und der
 * nächste Abgleich würde eine Eingabe hier ohnehin überschreiben.
 */
beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('legt eine Domain von Hand an', function (): void {
    $domain = app(CreateManualDomain::class)([
        'name' => ' Beispiel.DE. ',
        'status' => 'aktiv',
        'registered_on' => '2020-03-01',
        'expires_on' => '2027-03-01',
        'auto_renew' => true,
        'nameservers' => ['ns1.example.net'],
    ]);

    expect($domain->name)->toBe('beispiel.de')
        ->and($domain->provider)->toBe(RegistrarProvider::Manual)
        ->and($domain->isMaintainedByHand())->toBeTrue()
        ->and($domain->expires_on->toDateString())->toBe('2027-03-01')
        ->and($domain->auto_renew)->toBeTrue()
        ->and($domain->nameservers)->toBe(['ns1.example.net'])
        // Abgeglichen wurde nichts: ein Datum hier hieße, ein Anbieter habe
        // geantwortet.
        ->and($domain->synced_at)->toBeNull()
        ->and($domain->provider_reference)->toBeNull();
});

it('legt eine Domain ueber das Formular an und fuehrt auf ihre Detailseite', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(DomainForm::class)
        ->set('name', 'Von-Hand.DE')
        ->set('status', 'aktiv')
        ->set('expires_on', '2027-06-30')
        ->set('auto_renew', true)
        ->set('nameservers', "NS1.Example.NET\nns2.example.net.\n\nns1.example.net")
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('domains.show', Domain::query()->sole()));

    $domain = Domain::query()->sole();

    expect($domain->name)->toBe('von-hand.de')
        ->and($domain->provider)->toBe(RegistrarProvider::Manual)
        // Klein, ohne Punkt am Ende, ohne Doppelte, leere Zeilen weg.
        ->and($domain->nameservers)->toBe(['ns1.example.net', 'ns2.example.net']);
});

it('trennt Nameserver auch an Kommata', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(DomainForm::class)
        ->set('name', 'kommas.de')
        ->set('nameservers', 'ns1.example.net, ns2.example.net')
        ->call('save')
        ->assertHasNoErrors();

    expect(Domain::query()->sole()->nameservers)->toBe(['ns1.example.net', 'ns2.example.net']);
});

it('weist eine Eingabe ohne Endung ab', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(DomainForm::class)
        ->set('name', 'beispiel')
        ->call('save')
        ->assertHasErrors(['name']);

    expect(Domain::query()->count())->toBe(0);
});

it('weist einen Namen ab, den es schon gibt', function (): void {
    Domain::factory()->create(['name' => 'schon-da.de']);

    Livewire::actingAs($this->benutzer)
        ->test(DomainForm::class)
        // Auch in anderer Schreibweise: gespeichert wird kleingeschrieben, und
        // `name` ist eindeutig.
        ->set('name', 'Schon-Da.DE')
        ->call('save')
        ->assertHasErrors(['name']);

    expect(Domain::query()->count())->toBe(1);
});

it('weist einen Ablauf vor der Registrierung ab', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(DomainForm::class)
        ->set('name', 'rueckwaerts.de')
        ->set('registered_on', '2026-01-01')
        ->set('expires_on', '2025-01-01')
        ->call('save')
        ->assertHasErrors(['expires_on']);
});

it('prueft den Ablauf nicht gegen ein leeres Registrierungsdatum', function (): void {
    // Sonst käme eine Meldung über ein Datum, das niemand eingegeben hat.
    Livewire::actingAs($this->benutzer)
        ->test(DomainForm::class)
        ->set('name', 'ohne-anfang.de')
        ->set('expires_on', '2027-01-01')
        ->call('save')
        ->assertHasNoErrors();
});

it('aendert eine von Hand gepflegte Domain', function (): void {
    $domain = Domain::factory()->manual()->create([
        'name' => 'alt.de',
        'nameservers' => ['ns1.example.net'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(DomainForm::class, ['domain' => $domain])
        // Derselbe Name muss beim Bearbeiten stehen bleiben dürfen.
        ->assertSet('name', 'alt.de')
        ->set('status', 'gekündigt')
        ->set('nameservers', 'ns9.example.net')
        ->call('save')
        ->assertHasNoErrors();

    $domain->refresh();

    expect($domain->status)->toBe('gekündigt')
        ->and($domain->nameservers)->toBe(['ns9.example.net'])
        // Der Anbieter bleibt: aus einer von Hand gepflegten Domain wird hier
        // keine importierte.
        ->and($domain->provider)->toBe(RegistrarProvider::Manual);
});

it('laesst den technischen Stand einer importierten Domain nicht aendern', function (): void {
    $domain = Domain::factory()->create(['provider' => RegistrarProvider::AutoDns]);

    expect(fn () => app(UpdateManualDomain::class)($domain, ['name' => 'entfuehrt.de']))
        ->toThrow(ReadOnlyRecordException::class, 'wird dort geändert');

    expect($domain->refresh()->name)->not->toBe('entfuehrt.de');
});

it('gibt das Formular einer importierten Domain nicht heraus', function (): void {
    $domain = Domain::factory()->create(['provider' => RegistrarProvider::ResellerInterface]);

    $this->actingAs($this->benutzer)
        ->get(route('domains.edit', $domain))
        ->assertForbidden();
});

it('zeigt den Knopf zum Bearbeiten nur bei von Hand gepflegten Domains', function (): void {
    $vonHand = Domain::factory()->manual()->create();
    $importiert = Domain::factory()->create(['provider' => RegistrarProvider::AutoDns]);

    $this->actingAs($this->benutzer)
        ->get(route('domains.show', $vonHand))
        ->assertSee(route('domains.edit', $vonHand));

    $this->actingAs($this->benutzer)
        ->get(route('domains.show', $importiert))
        ->assertDontSee(route('domains.edit', $importiert));
});

it('uebernimmt eine von Hand angelegte Domain, wenn der Registrar sie fuehrt', function (): void {
    $kunde = Customer::factory()->create();

    $domain = Domain::factory()->manual()->create([
        'name' => 'wandert.de',
        'customer_id' => $kunde->getKey(),
    ]);

    /*
     * Der Abgleich findet sie über den Namen und übernimmt sie — samt der von
     * Hand gesetzten Zuordnung. Genau so soll es sein: die Domain liegt jetzt
     * wirklich bei diesem Anbieter, und zwei Datensätze für denselben Namen
     * gibt es nicht.
     */
    $client = new class implements RegistrarClient
    {
        public function provider(): RegistrarProvider
        {
            return RegistrarProvider::AutoDns;
        }

        public function isConfigured(): bool
        {
            return true;
        }

        public function testConnection(): string
        {
            return 'ok';
        }

        public function domains(): iterable
        {
            return [new RemoteDomain(
                name: 'wandert.de',
                reference: 'A-4711',
                status: 'ACTIVE',
            )];
        }

        public function certificates(): iterable
        {
            return [];
        }

        public function canReadZone(): bool
        {
            return false;
        }

        public function zone(string $domain): DnsZone
        {
            throw new RegistrarException('nicht vorgesehen');
        }
    };

    $ergebnis = app(ImportRegistrarInventory::class)($client);

    expect($ergebnis['domains'])->toBe(['new' => 0, 'updated' => 1]);

    $domain->refresh();

    expect(Domain::query()->count())->toBe(1)
        ->and($domain->provider)->toBe(RegistrarProvider::AutoDns)
        ->and($domain->provider_reference)->toBe('A-4711')
        ->and($domain->customer_id)->toBe($kunde->getKey());
});

it('baut fuer von Hand gepflegte Domains keinen Anschluss', function (): void {
    expect(fn () => app(RegistrarClientFactory::class)->for(RegistrarProvider::Manual))
        ->toThrow(RegistrarException::class, 'kein Anschluss');
});

it('bietet keine Zugangsdaten fuer von Hand gepflegte Domains an', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->assertSee('autoDNS')
        ->assertDontSee('Von Hand gepflegt');
});

it('importiert nichts fuer von Hand gepflegte Domains', function (): void {
    $this->artisan('registrar:import', ['anbieter' => 'manual'])
        ->expectsOutputToContain('von Hand gepflegt')
        ->assertFailed();
});

it('prueft keine Verbindung fuer von Hand gepflegte Domains', function (): void {
    $this->artisan('registrar:test', ['anbieter' => 'manual'])
        ->expectsOutputToContain('von Hand gepflegt')
        ->assertFailed();
});
