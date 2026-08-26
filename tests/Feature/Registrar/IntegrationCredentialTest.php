<?php

use App\Enums\RegistrarProvider;
use App\Livewire\System\IntegrationSettings;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Support\Registrar\RegistrarClientFactory;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('legt Zugangsdaten verschluesselt ab', function (): void {
    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::Inwx->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'sehr-geheim'],
    ]);

    $roh = DB::table('integration_credentials')->value('credentials');

    // In der Datenbank darf nie Klartext stehen.
    expect($roh)->not->toContain('sehr-geheim')
        ->and($roh)->not->toContain('benutzer')
        ->and(IntegrationCredential::valuesFor(RegistrarProvider::Inwx))
        ->toBe(['username' => 'benutzer', 'password' => 'sehr-geheim']);
});

it('setzt den Anschluss aus Endpunkt und hinterlegten Zugangsdaten zusammen', function (): void {
    expect(app(RegistrarClientFactory::class)->for(RegistrarProvider::Inwx)->isConfigured())->toBeFalse();

    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::Inwx->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    expect(app(RegistrarClientFactory::class)->for(RegistrarProvider::Inwx)->isConfigured())->toBeTrue()
        ->and(app(RegistrarClientFactory::class)->configured())->toHaveCount(1);
});

it('speichert eingegebene Zugangsdaten ueber die Oberflaeche', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->set('input.inwx.username', 'benutzer')
        ->set('input.inwx.password', 'geheim')
        ->call('save', 'inwx')
        ->assertDispatched('zugang-gespeichert');

    $eintrag = IntegrationCredential::query()->sole();

    expect($eintrag->credentials)->toBe(['username' => 'benutzer', 'password' => 'geheim'])
        ->and($eintrag->updated_by)->toBe($this->benutzer->id);
});

it('laesst beim Ersetzen eines Feldes die uebrigen stehen', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'inwx',
        'credentials' => ['username' => 'benutzer', 'password' => 'alt'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->set('input.inwx.password', 'neu')
        ->call('save', 'inwx');

    // Wer nur das Kennwort wechselt, soll den Benutzernamen nicht erneut tippen.
    expect(IntegrationCredential::valuesFor(RegistrarProvider::Inwx))
        ->toBe(['username' => 'benutzer', 'password' => 'neu']);
});

it('aendert nichts, wenn kein Feld ausgefuellt wurde', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'inwx',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->call('save', 'inwx')
        ->assertDispatched('zugang-unveraendert');

    expect(IntegrationCredential::valuesFor(RegistrarProvider::Inwx)['password'])->toBe('geheim');
});

it('entfernt Zugangsdaten vollstaendig', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'inwx',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->call('forget', 'inwx')
        ->assertDispatched('zugang-entfernt');

    expect(IntegrationCredential::valuesFor(RegistrarProvider::Inwx))->toBe([])
        ->and(app(RegistrarClientFactory::class)->for(RegistrarProvider::Inwx)->isConfigured())->toBeFalse();
});

it('liefert hinterlegte Werte nie an die Oberflaeche zurueck', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'inwx',
        'credentials' => ['username' => 'benutzer', 'password' => 'sehr-geheim'],
    ]);

    $komponente = Livewire::actingAs($this->benutzer)->test(IntegrationSettings::class);

    // Weder im Zustand der Komponente noch im gerenderten Markup.
    $komponente->assertSet('input.inwx.password', '')
        ->assertDontSee('sehr-geheim')
        ->assertSee('Hinterlegt');
});

it('zeigt an, welcher Anschluss eingerichtet ist', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'inwx',
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(IntegrationSettings::class)
        ->assertSee('Eingerichtet')
        ->assertSee('Nicht eingerichtet');
});

it('haelt Zugangsdaten aus der Aenderungshistorie heraus', function (): void {
    IntegrationCredential::query()->create([
        'provider' => 'inwx',
        'credentials' => ['username' => 'benutzer', 'password' => 'sehr-geheim'],
    ]);

    // Ein Kennwort gehört nicht in ein Protokoll, das alte und neue Werte hält.
    $eintraege = DB::table('audit_logs')->get()->map(fn ($zeile): string => json_encode($zeile, JSON_UNESCAPED_UNICODE) ?: '');

    expect($eintraege->filter(fn (string $zeile): bool => str_contains($zeile, 'sehr-geheim')))->toBeEmpty();
});
