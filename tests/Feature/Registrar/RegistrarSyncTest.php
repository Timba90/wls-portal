<?php

use App\Actions\Registrar\SyncRegistrarInventory;
use App\Enums\RegistrarProvider;
use App\Livewire\System\IntegrationSettings;
use App\Models\Domain;
use App\Models\IntegrationCredential;
use App\Models\RegistrarSync;
use App\Models\User;
use App\Support\Registrar\AutoDnsClient;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Der tägliche Abgleich (§60).
 *
 * Der eigentliche Punkt ist nicht das Einlesen — das konnte der Import schon.
 * Neu ist, dass jeder Lauf ein Protokoll hinterlässt: ein Abgleich, der nachts
 * still scheitert, sieht am nächsten Morgen aus wie ein Bestand ohne
 * Änderungen.
 */
function abgleichClient(): AutoDnsClient
{
    return new AutoDnsClient([
        'endpoint' => 'https://api.example.test/v1/',
        'username' => 'benutzer',
        'password' => 'geheim',
        'context' => '4',
    ]);
}

it('haelt einen gelungenen Lauf mit Zahlen fest', function (): void {
    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'S0301', 'type' => 'SUCCESS', 'text' => 'ok'],
        'data' => [['name' => 'beispiel.de', 'expire' => '2027-03-04T10:00:00.000+0100']],
    ]));

    $lauf = app(SyncRegistrarInventory::class)(abgleichClient());

    expect($lauf->isFailed())->toBeFalse()
        ->and($lauf->provider)->toBe(RegistrarProvider::AutoDns)
        ->and($lauf->trigger)->toBe('scheduled')
        ->and($lauf->domains_new)->toBe(1)
        ->and($lauf->finished_at)->not->toBeNull()
        ->and(Domain::query()->count())->toBe(1);
});

it('haelt einen Fehlschlag mit der Meldung des Anbieters fest', function (): void {
    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'EF01001', 'type' => 'ERROR', 'text' => 'Authorization failed'],
    ], 401));

    // Kein Abbruch nach oben: der Zeitplan soll weiterlaufen, das Protokoll
    // den Fehlschlag tragen.
    $lauf = app(SyncRegistrarInventory::class)(abgleichClient());

    expect($lauf->isFailed())->toBeTrue()
        ->and($lauf->error)->toContain('Authorization failed')
        ->and($lauf->finished_at)->not->toBeNull()
        ->and($lauf->touched())->toBe(0);
});

it('wiederholt einen fehlgeschlagenen Lauf nicht', function (): void {
    Http::fake(fn () => Http::response(['status' => ['type' => 'ERROR', 'text' => 'TOO_MANY_ATTEMPTS']], 429));

    app(SyncRegistrarInventory::class)(abgleichClient());

    // Jeder weitere Versuch verlängerte bei ResellerInterface eine Sperre.
    Http::assertSentCount(1);
});

it('gleicht ueber den Befehl alle eingerichteten Anbieter ab', function (): void {
    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'geheim'],
    ]);

    Http::fake(fn () => Http::response([
        'stid' => 'x',
        'status' => ['code' => 'S0301', 'type' => 'SUCCESS', 'text' => 'ok'],
        'data' => [],
    ]));

    $this->artisan('registrar:sync')->assertSuccessful();

    expect(RegistrarSync::query()->sole()->trigger)->toBe('scheduled');
});

it('meldet einen Fehlschlag auch nach aussen', function (): void {
    IntegrationCredential::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'credentials' => ['username' => 'benutzer', 'password' => 'falsch'],
    ]);

    Http::fake(fn () => Http::response([
        'status' => ['code' => 'EF01001', 'type' => 'ERROR', 'text' => 'Authorization failed'],
    ], 401));

    // Der Zeitplan soll den Fehlschlag sehen.
    $this->artisan('registrar:sync')->assertFailed();
});

it('laeuft ohne eingerichteten Anbieter durch, statt zu scheitern', function (): void {
    $this->artisan('registrar:sync')
        ->expectsOutputToContain('Kein Anbieter eingerichtet')
        ->assertSuccessful();

    expect(RegistrarSync::query()->count())->toBe(0);
});

it('zeigt den letzten Lauf unter Schnittstellen', function (): void {
    RegistrarSync::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'trigger' => 'scheduled',
        'started_at' => now()->subHours(6),
        'finished_at' => now()->subHours(6),
        'domains_new' => 3,
        'domains_updated' => 12,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(IntegrationSettings::class)
        ->assertSee('Zuletzt abgeglichen')
        ->assertSee('3 neu')
        ->assertSee('12 geändert');
});

it('zeigt einen Fehlschlag im Wortlaut, statt ihn zu verschweigen', function (): void {
    RegistrarSync::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'trigger' => 'scheduled',
        'started_at' => now()->subHours(6),
        'finished_at' => now()->subHours(6),
        'error' => 'autoDNS meldet zu domain/_search: Authorization failed (EF01001)',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(IntegrationSettings::class)
        ->assertSee('Letzter Abgleich fehlgeschlagen')
        ->assertSee('Authorization failed');
});

it('nimmt je Anbieter den juengsten Lauf', function (): void {
    foreach ([3, 1, 2] as $vorTagen) {
        RegistrarSync::query()->create([
            'provider' => RegistrarProvider::AutoDns->value,
            'trigger' => 'scheduled',
            'started_at' => now()->subDays($vorTagen),
            'finished_at' => now()->subDays($vorTagen),
            'domains_new' => $vorTagen,
        ]);
    }

    $letzter = Livewire::actingAs(User::factory()->create())
        ->test(IntegrationSettings::class)
        ->instance()
        ->lastSync(RegistrarProvider::AutoDns);

    expect($letzter->domains_new)->toBe(1);
});

it('steht im Zeitplan, damit ihn niemand aufrufen muss', function (): void {
    // Der ganze Sinn: er laeuft ohne Zutun. Faellt der Eintrag heraus, faellt
    // das sonst erst auf, wenn der Bestand veraltet ist.
    $eintrag = collect(app(Schedule::class)->events())
        ->first(fn (Event $ereignis): bool => str_contains($ereignis->command ?? '', 'registrar:sync'));

    expect($eintrag)->not->toBeNull()
        ->and($eintrag->expression)->toBe('20 3 * * *');
});
