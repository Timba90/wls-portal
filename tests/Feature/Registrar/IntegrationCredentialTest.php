<?php

use App\Enums\RegistrarProvider;
use App\Livewire\System\IntegrationSettings;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Support\Registrar\RegistrarClientFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

/**
 * Die Eingabeart des Feldes, an dem `wire:model` auf diesen Namen zeigt.
 */
function feldTyp(string $markup, string $feld): ?string
{
    if (preg_match('/<input\\b[^>]*wire:model="input\\.autodns\\.'.$feld.'"[^>]*>/', $markup, $treffer) !== 1) {
        return null;
    }

    return preg_match('/\\btype="([^"]+)"/', $treffer[0], $art) === 1 ? $art[1] : null;
}

it('legt Zugangsdaten verschluesselt ab', function (): void {
    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'sehr-geheim'],
    ]);

    $roh = DB::table('integration_credentials')->value('credentials');

    // In der Datenbank darf nie Klartext stehen.
    expect($roh)->not->toContain('sehr-geheim')
        ->and($roh)->not->toContain('benutzer')
        ->and(IntegrationCredential::valuesFor(RegistrarProvider::AutoDns))
        ->toBe(['username' => 'benutzer', 'password' => 'sehr-geheim']);
});

it('setzt den Anschluss aus Endpunkt und hinterlegten Zugangsdaten zusammen', function (): void {
    expect(app(RegistrarClientFactory::class)->for(RegistrarProvider::AutoDns)->isConfigured())->toBeFalse();

    // Der Kontext kommt aus der Konfiguration; einzutippen sind nur
    // Benutzername und Kennwort.
    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    expect(app(RegistrarClientFactory::class)->for(RegistrarProvider::AutoDns)->isConfigured())->toBeTrue()
        ->and(app(RegistrarClientFactory::class)->configured())->toHaveCount(1);
});

it('nimmt den Kontext 4, ohne dass ihn jemand eintippt', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'S0101', 'type' => 'SUCCESS', 'text' => 'Hallo.'],
    ]));

    app(RegistrarClientFactory::class)->for(RegistrarProvider::AutoDns)->testConnection();

    Http::assertSent(fn (Request $anfrage): bool => $anfrage->hasHeader('X-Domainrobot-Context', '4'));
});

it('laesst einen hinterlegten Kontext vor die Voreinstellung', function (): void {
    // Fürs Testsystem von autoDNS gilt 1; das gehört einstellbar zu bleiben.
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim', 'context' => '1'],
    ]);

    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'S0101', 'type' => 'SUCCESS', 'text' => 'Hallo.'],
    ]));

    app(RegistrarClientFactory::class)->for(RegistrarProvider::AutoDns)->testConnection();

    Http::assertSent(fn (Request $anfrage): bool => $anfrage->hasHeader('X-Domainrobot-Context', '1'));
});

it('speichert eingegebene Zugangsdaten ueber die Oberflaeche', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->set('input.autodns.username', 'benutzer')
        ->set('input.autodns.password', 'geheim')
        ->call('save', 'autodns')
        ->assertDispatched('zugang-gespeichert');

    $eintrag = IntegrationCredential::query()->sole();

    expect($eintrag->credentials)->toBe(['username' => 'benutzer', 'password' => 'geheim'])
        ->and($eintrag->updated_by)->toBe($this->benutzer->id);
});

it('laesst beim Ersetzen eines Feldes die uebrigen stehen', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'alt'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->set('input.autodns.password', 'neu')
        ->call('save', 'autodns');

    // Wer nur das Kennwort wechselt, soll den Benutzernamen nicht erneut tippen.
    expect(IntegrationCredential::valuesFor(RegistrarProvider::AutoDns))
        ->toBe(['username' => 'benutzer', 'password' => 'neu']);
});

it('aendert nichts, wenn kein Feld ausgefuellt wurde', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->call('save', 'autodns')
        ->assertDispatched('zugang-unveraendert');

    expect(IntegrationCredential::valuesFor(RegistrarProvider::AutoDns)['password'])->toBe('geheim');
});

it('entfernt Zugangsdaten vollstaendig', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->call('forget', 'autodns')
        ->assertDispatched('zugang-entfernt');

    expect(IntegrationCredential::valuesFor(RegistrarProvider::AutoDns))->toBe([])
        ->and(app(RegistrarClientFactory::class)->for(RegistrarProvider::AutoDns)->isConfigured())->toBeFalse();
});

it('liefert hinterlegte Werte nie an die Oberflaeche zurueck', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'sehr-geheim'],
    ]);

    $komponente = Livewire::actingAs($this->benutzer)->test(IntegrationSettings::class);

    // Weder im Zustand der Komponente noch im gerenderten Markup.
    $komponente->assertSet('input.autodns.password', '')
        ->assertDontSee('sehr-geheim')
        ->assertSee('Hinterlegt');
});

it('zeigt an, ob der Anschluss eingerichtet ist', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->assertSee('Nicht eingerichtet');

    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim', 'context' => '4'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->assertSee('Eingerichtet');
});

it('bietet den Verbindungstest erst an, wenn der Anschluss vollstaendig ist', function (): void {
    // Ein Testknopf ohne Zugangsdaten führte nur zu einer Fehlermeldung.
    // Geprüft wird der Knopf, nicht der Text — der Hilfetext nennt ihn immer.
    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->assertDontSeeHtml('wire:click="test(');

    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->assertSeeHtml('wire:click="test(')
        ->assertSee('Verbindung prüfen');
});

it('haelt Zugangsdaten aus der Aenderungshistorie heraus', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'sehr-geheim'],
    ]);

    // Ein Kennwort gehört nicht in ein Protokoll, das alte und neue Werte hält.
    $eintraege = DB::table('audit_logs')->get()->map(fn ($zeile): string => json_encode($zeile, JSON_UNESCAPED_UNICODE) ?: '');

    expect($eintraege->filter(fn (string $zeile): bool => str_contains($zeile, 'sehr-geheim')))->toBeEmpty();
});

it('zeigt den Kontext im Klartext, das Kennwort nicht', function (): void {
    // Der Kontext ist kein Geheimnis; maskiert faellt ein Zahlendreher nicht auf.
    $markup = Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->html();

    expect(feldTyp($markup, 'context'))->toBe('text')
        ->and(feldTyp($markup, 'password'))->toBe('password')
        ->and(feldTyp($markup, 'username'))->toBe('password');
});

it('meldet einen bestandenen Verbindungstest an die Oberflaeche', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim', 'context' => '4'],
    ]);

    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'S0101', 'type' => 'SUCCESS', 'text' => 'Hallo.'],
    ]));

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->call('test', 'autodns')
        ->assertDispatched('zugang-geprueft');
});

it('meldet eine abgelehnte Verbindung, ohne die Seite zu brechen', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'autodns',
        'credentials' => ['username' => 'benutzer', 'password' => 'falsch', 'context' => '4'],
    ]);

    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'EF01001', 'type' => 'ERROR', 'text' => 'Authorization failed'],
    ], 401));

    // Eine falsche Eingabe darf eine Meldung geben, keine Ausnahme.
    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->call('test', 'autodns')
        ->assertDispatched('zugang-abgelehnt')
        ->assertOk();
});

it('verlangt fuer ResellerInterface keine Zugangsdaten', function (): void {
    // Sie liegen in der Brücke auf demselben Server. Ein zweiter Ort für
    // dieselben Geheimnisse wäre ein Risiko ohne Gegenwert.
    $komponente = Livewire::actingAs($this->benutzer)->test(IntegrationSettings::class);

    expect($komponente->instance()->fieldsFor(RegistrarProvider::ResellerInterface))->toBe([]);

    $komponente->assertSee('ResellerInterface')
        ->assertSee('Die Anmeldung übernimmt die Brücke')
        ->assertDontSeeHtml('wire:model="input.resellerinterface.');
});

it('nennt den Anschluss erst eingerichtet, wenn die Bruecke aufrufbar ist', function (): void {
    // Auf dem Testrechner gibt es sie nicht — dann darf auch nichts anderes
    // behauptet werden.
    $bereit = Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->instance()
        ->isReady(RegistrarProvider::ResellerInterface);

    expect($bereit)->toBe(is_executable((string) config('services.resellerinterface.command')));
});
