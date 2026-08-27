<?php

use App\Actions\Registrar\ImportRegistrarInventory;
use App\Enums\RegistrarProvider;
use App\Models\Domain;
use App\Support\Registrar\RegistrarException;
use App\Support\Registrar\ResellerInterfaceClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Der Anschluss meldet sich direkt beim Anbieter an. Die Regeln dafür
 * stammen aus einem echten Vorfall: ein selbstgebautes Login-Skript hat das
 * Konto gesperrt und damit die DNS-Änderungen aller Kunden blockiert.
 */
function anschluss(array $ueberschreibungen = []): ResellerInterfaceClient
{
    return new ResellerInterfaceClient(array_merge([
        'endpoint' => 'https://core.resellerinterface.de',
        'branch' => 'stable',
        'username' => 'benutzer',
        'password' => 'geheim',
        'test_domain' => 'freie-testdomain-xyz.de',
    ], $ueberschreibungen));
}

/**
 * Eine typische erfolgreiche Anmeldung: Umschlag plus coreSID-Cookie, wie
 * ihn der Anbieter per Set-Cookie-Kopf schickt.
 */
function anmeldungFaken(): void
{
    Http::fake([
        '*/reseller/login' => Http::response(
            ['success' => true, 'state' => 1000, 'stateName' => 'OK'],
            200,
            ['Set-Cookie' => 'coreSID=sitzung-123; Path=/; HttpOnly'],
        ),
    ]);
}

it('haelt einen Anschluss ohne Zugangsdaten fuer nicht eingerichtet', function (): void {
    expect(anschluss(['username' => null, 'password' => null])->isConfigured())->toBeFalse()
        ->and(anschluss()->isConfigured())->toBeTrue();
});

it('meldet sich beim ersten Aufruf an und haelt die Sitzung im Cache', function (): void {
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::response(['success' => true, 'total' => 1, 'list' => [
        ['domain' => 'Beispiel.DE', 'domainID' => 4711, 'state' => 'ACTIVE'],
    ]])]);

    $domains = iterator_to_array(anschluss()->domains());

    expect($domains)->toHaveCount(1)
        ->and($domains[0]->name)->toBe('beispiel.de')
        ->and(Cache::get('registrar.resellerinterface.session.haupt'))->toBe('sitzung-123');

    // Der zweite Aufruf nutzt die Sitzung und meldet sich nicht erneut an.
    iterator_to_array(anschluss()->domains());

    Http::assertSentCount(3); // 1 Login + 2 Listen
});

it('ruft domain/list mit dem Limit 1000 ab, nicht mit der Voreinstellung 25', function (): void {
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::response(['success' => true, 'total' => 1, 'list' => [
        ['domain' => 'beispiel.de'],
    ]])]);

    iterator_to_array(anschluss()->domains());

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'domain/list')
            && ($request['limit'] ?? null) === 1000;
    });
});

it('blaetert, bis die Gesamtzahl erreicht ist', function (): void {
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::sequence([
        Http::response(['success' => true, 'total' => 3, 'list' => [
            ['domain' => 'eins.de'], ['domain' => 'zwei.de'],
        ]]),
        Http::response(['success' => true, 'total' => 3, 'list' => [
            ['domain' => 'drei.de'],
        ]]),
    ])]);

    $domains = iterator_to_array(anschluss()->domains());

    expect($domains)->toHaveCount(3)
        ->and($domains[2]->name)->toBe('drei.de');
});

it('liest auch den Bestand der Subreseller, aber ohne den Hauptaccount doppelt', function (): void {
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::response(['success' => true, 'total' => 0, 'list' => []])]);

    iterator_to_array(anschluss(['reseller_id' => '58919', 'reseller_ids' => '58919, 59163'])->domains());

    // Hauptaccount (per konfigurierter resellerID) und Subreseller 59163 —
    // die doppelt genannte 58919 aus der Liste ist entfernt worden.
    Http::assertSentCount(3); // 1 Login + 2 Listen

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'domain/list')
            && ($request['resellerID'] ?? null) === '59163';
    });

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'domain/list')
            && ($request['resellerID'] ?? null) === '58919';
    });
});

it('schickt den Benutzernamen im Login, aber nie in der Bestandsabfrage', function (): void {
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::response(['success' => true, 'total' => 0, 'list' => []])]);

    iterator_to_array(anschluss()->domains());

    Http::assertSent(function ($request): bool {
        if (str_contains($request->url(), 'reseller/login')) {
            return $request['username'] === 'benutzer' && $request['password'] === 'geheim';
        }

        return ! isset($request['username']) && ! isset($request['password']);
    });
});

it('prueft die Verbindung ueber tld/list, ohne den Bestand zu lesen', function (): void {
    anmeldungFaken();
    Http::fake(['*/tld/list' => Http::response(['success' => true, 'stateName' => 'OK'])]);

    expect(anschluss()->testConnection())->toContain('OK');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'tld/list'));
    Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'domain/list'));
});

it('bricht bei einer Kontosperre unmissverstaendlich ab', function (): void {
    Http::fake(['*' => Http::response(['success' => false, 'stateName' => 'TOO_MANY_ATTEMPTS'])]);

    iterator_to_array(anschluss()->domains());
})->throws(RegistrarException::class, 'Das Konto ist gesperrt oder kurz davor');

it('wiederholt einen fehlgeschlagenen Aufruf nicht', function (): void {
    Http::fake(['*/reseller/login' => Http::response(['success' => false, 'stateName' => 'WRONG_USERNAME_OR_PASSWORD'])]);

    try {
        iterator_to_array(anschluss()->domains());
    } catch (RegistrarException) {
        // erwartet
    }

    // Jeder weitere Versuch verlängert die Sperre: genau ein Aufruf.
    Http::assertSentCount(1);
});

it('meldet sich genau einmal neu an, wenn die Sitzung abgelaufen ist', function (): void {
    Cache::put('registrar.resellerinterface.session.haupt', 'abgelaufen', now()->addMinutes(15));

    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::sequence([
        // Erster Versuch mit alter Sitzung: abgelaufen.
        Http::response(['success' => false, 'state' => 4000, 'stateName' => 'SESSION_EXPIRED']),
        // Nach der Neuanmeldung klappt es.
        Http::response(['success' => true, 'total' => 1, 'list' => [
            ['domain' => 'beispiel.de'],
        ]]),
    ])]);

    $domains = iterator_to_array(anschluss()->domains());

    expect($domains)->toHaveCount(1)
        ->and(Cache::get('registrar.resellerinterface.session.haupt'))->toBe('sitzung-123');
});

it('laesst keine schreibende Aktion zu', function (): void {
    Http::fake(['*' => Http::response(['success' => true])]);

    $anschluss = anschluss();
    $aufrufen = (new ReflectionClass($anschluss))->getMethod('call');

    expect(fn () => $aufrufen->invoke($anschluss, 'domain/transfer', ['domain' => 'beispiel.de']))
        ->toThrow(RegistrarException::class, 'nicht vorgesehen');

    // Der verbotene Aufruf darf den Anbieter gar nicht erst erreichen.
    Http::assertNothingSent();
});

it('bricht ab, wenn die Antwort keine Liste enthaelt', function (): void {
    // Weder unter `list` noch unter `data.list` haelt etwas — stillschweigend
    // nichts einzulesen wäre schlimmer als ein Abbruch.
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::response(['success' => true, 'total' => 5, 'andere_felder' => []])]);

    iterator_to_array(anschluss()->domains());
})->throws(RegistrarException::class, 'keine Liste');

it('fuehrt keine Zertifikate', function (): void {
    expect(iterator_to_array(anschluss()->certificates()))->toBe([]);
});

it('uebertraegt die Felder aus der Bestandsliste', function (): void {
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::response([
        'success' => true,
        'total' => 2,
        'list' => [
            [
                'domain' => 'Beispiel.DE',
                'domainID' => 4711,
                'state' => 'ACTIVE',
                'subState' => 'PENDING',
                'createDate' => '1785165050',
                'latestCancellationDate' => '1816613748',
                'cancellationDate' => null,
                'deleteMode' => '',
            ],
            [
                'domain' => 'gekuendigt.de',
                'domainID' => 4712,
                'state' => 'INACTIVE',
                'subState' => 'REVOKED',
                'cancellationDate' => '1787810102',
                'deleteMode' => 'delete',
            ],
        ],
    ])]);
    $domains = iterator_to_array(anschluss()->domains());

    expect($domains)->toHaveCount(2)
        ->and($domains[0]->name)->toBe('beispiel.de')
        ->and($domains[0]->reference)->toBe('4711')
        ->and($domains[0]->status)->toBe('ACTIVE PENDING')
        ->and($domains[0]->registeredOn?->timestamp)->toBe(1785165050)
        ->and($domains[0]->expiresOn?->timestamp)->toBe(1816613748)
        ->and($domains[0]->autoRenew)->toBeTrue()
        ->and($domains[0]->nameservers)->toBe([])
        ->and($domains[1]->autoRenew)->toBeFalse();
});

it('legt den Bestand ueber den gemeinsamen Abgleich an', function (): void {
    anmeldungFaken();
    Http::fake(['*/domain/list' => Http::response(['success' => true, 'total' => 1, 'list' => [
        ['domain' => 'beispiel.de', 'domainID' => '4711', 'latestCancellationDate' => '1816613748'],
    ]])]);

    $ergebnis = app(ImportRegistrarInventory::class)(anschluss());

    expect($ergebnis['domains'])->toBe(['new' => 1, 'updated' => 0]);

    $domain = Domain::query()->sole();

    expect($domain->provider)->toBe(RegistrarProvider::ResellerInterface)
        ->and($domain->provider_reference)->toBe('4711')
        ->and($domain->expires_on->toDateString())->toBe('2027-07-26');
});
