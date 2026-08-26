<?php

use App\Actions\Registrar\ImportRegistrarInventory;
use App\Enums\RegistrarProvider;
use App\Models\Domain;
use App\Support\Registrar\RegistrarException;
use App\Support\Registrar\ResellerInterfaceClient;
use Illuminate\Support\Facades\Process;

/**
 * Der Anschluss ruft die Brücke auf demselben Host auf und meldet sich nie
 * selbst beim Anbieter an. Die Regeln dafür stammen aus einem echten Vorfall:
 * ein selbstgebautes Login-Skript hat das Konto gesperrt und damit die
 * DNS-Änderungen aller Kunden blockiert.
 */
function bruecke(array $ueberschreibungen = []): ResellerInterfaceClient
{
    return new ResellerInterfaceClient(array_merge([
        // Ein Programm, das es gibt und das ausführbar ist — der Aufruf selbst
        // wird ohnehin abgefangen.
        'command' => '/bin/echo',
        'test_domain' => 'freie-testdomain-xyz.de',
    ], $ueberschreibungen));
}

/**
 * @param  array<int, array<string, mixed>>  $domains
 */
function bitteAntworten(array $inhalt): void
{
    Process::fake(['*' => Process::result(json_encode($inhalt) ?: '')]);
}

it('haelt einen Anschluss ohne aufrufbare Bruecke fuer nicht eingerichtet', function (): void {
    expect(bruecke(['command' => '/gibt/es/nicht'])->isConfigured())->toBeFalse()
        ->and(bruecke()->isConfigured())->toBeTrue();
});

it('nennt beim fehlenden Programm den eigenen Login nicht als Ausweg', function (): void {
    bruecke(['command' => '/gibt/es/nicht'])->domains()->current();
})->throws(RegistrarException::class, 'ein eigener Login beim Anbieter kommt nicht in Frage');

it('prueft die Verbindung ueber domain/check, ohne etwas zu lesen', function (): void {
    bitteAntworten(['success' => true, 'stateName' => 'OK']);

    expect(bruecke()->testConnection())->toContain('OK');

    Process::assertRan(function ($prozess): bool {
        $befehl = is_array($prozess->command) ? implode(' ', $prozess->command) : (string) $prozess->command;

        return str_contains($befehl, 'domain/check')
            && str_contains($befehl, 'freie-testdomain-xyz.de')
            && ! str_contains($befehl, 'domain/list');
    });
});

it('liest den Bestand mit dem Limit 1000, nicht mit der Voreinstellung 25', function (): void {
    bitteAntworten(['success' => true, 'data' => ['list' => [
        ['domain' => 'Beispiel.DE', 'status' => 'ACTIVE', 'expire' => '2027-03-04'],
    ]]]);

    $domains = iterator_to_array(bruecke()->domains());

    expect($domains)->toHaveCount(1)
        ->and($domains[0]->name)->toBe('beispiel.de');

    // Ohne das Limit fehlen mehrere hundert Domains und der Bestand sieht aus,
    // als wäre er verschwunden.
    Process::assertRan(fn ($prozess): bool => str_contains(
        is_array($prozess->command) ? implode(' ', $prozess->command) : (string) $prozess->command,
        '"limit":1000',
    ));
});

it('liest auch den Bestand der Subreseller', function (): void {
    bitteAntworten(['success' => true, 'data' => ['list' => []]]);

    iterator_to_array(bruecke(['reseller_ids' => '59163, 12345'])->domains());

    // Ohne resellerID liefert domain/list nur den Hauptaccount.
    Process::assertRanTimes(fn (): bool => true, 3);

    foreach (['59163', '12345'] as $id) {
        Process::assertRan(fn ($prozess): bool => str_contains(
            is_array($prozess->command) ? implode(' ', $prozess->command) : (string) $prozess->command,
            '"resellerID":"'.$id.'"',
        ));
    }
});

it('bricht bei einer Kontosperre unmissverstaendlich ab', function (): void {
    bitteAntworten(['success' => false, 'stateName' => 'TOO_MANY_ATTEMPTS']);

    iterator_to_array(bruecke()->domains());
})->throws(RegistrarException::class, 'Das Konto ist gesperrt oder kurz davor');

it('wiederholt einen fehlgeschlagenen Aufruf nicht', function (): void {
    bitteAntworten(['success' => false, 'stateName' => 'WRONG_USERNAME_OR_PASSWORD']);

    try {
        iterator_to_array(bruecke()->domains());
    } catch (RegistrarException) {
        // erwartet
    }

    // Jeder weitere Versuch verlängert die Sperre.
    Process::assertRanTimes(fn (): bool => true, 1);
});

it('laesst keine schreibende Aktion zu', function (): void {
    bitteAntworten(['success' => true]);

    $anschluss = bruecke();
    $aufrufen = (new ReflectionClass($anschluss))->getMethod('call');

    expect(fn () => $aufrufen->invoke($anschluss, 'domain/transfer', ['domain' => 'beispiel.de']))
        ->toThrow(RegistrarException::class, 'nicht vorgesehen');

    // Der verbotene Aufruf darf die Brücke gar nicht erst erreichen.
    Process::assertNothingRan();
});

it('bricht ab, wenn die Antwort keine Liste enthaelt', function (): void {
    // Bei den Preisen lag die Liste unter data.list, während die alte
    // Beispielantwort etwas anderes zeigte — stillschweigend nichts einzulesen
    // wäre schlimmer als ein Abbruch.
    bitteAntworten(['success' => true, 'data' => ['tld' => []]]);

    iterator_to_array(bruecke()->domains());
})->throws(RegistrarException::class, 'keine Liste unter data.list');

it('fuehrt keine Zertifikate', function (): void {
    expect(iterator_to_array(bruecke()->certificates()))->toBe([]);
});

it('legt den Bestand ueber den gemeinsamen Abgleich an', function (): void {
    bitteAntworten(['success' => true, 'data' => ['list' => [
        ['domain' => 'beispiel.de', 'domainID' => '4711', 'expire' => '2027-03-04'],
    ]]]);

    $ergebnis = app(ImportRegistrarInventory::class)(bruecke());

    expect($ergebnis['domains'])->toBe(['new' => 1, 'updated' => 0]);

    $domain = Domain::query()->sole();

    expect($domain->provider)->toBe(RegistrarProvider::ResellerInterface)
        ->and($domain->provider_reference)->toBe('4711')
        ->and($domain->expires_on->toDateString())->toBe('2027-03-04');
});
