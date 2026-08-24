<?php

use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Customers\KundenSuchen;
use App\Models\Customer;
use App\Models\User;

it('weist Aufrufe ohne Token ab', function (): void {
    $this->postJson(route('mcp.portal'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

it('lässt Aufrufe mit gültigem Token zu', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('mcp.portal'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertOk()
        ->assertJsonPath('result.tools.0.name', 'kunden-suchen');
});

it('weist widerrufene Tokens ab', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Test');
    $klartext = $token->plainTextToken;

    $token->accessToken->delete();

    $this->withHeader('Authorization', 'Bearer '.$klartext)
        ->postJson(route('mcp.portal'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
});

it('beschreibt jedes Werkzeug mit Name, Titel und Beschreibung', function (): void {
    $user = User::factory()->create();

    $antwort = $this->withHeader('Authorization', 'Bearer '.$user->createToken('Test')->plainTextToken)
        ->postJson(route('mcp.portal'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertOk();

    $werkzeuge = $antwort->json('result.tools');

    expect($werkzeuge)->toHaveCount(38);

    foreach ($werkzeuge as $werkzeug) {
        expect($werkzeug['name'])->toMatch('/^[a-z]+(-[a-z]+)*$/')
            ->and($werkzeug['title'])->not->toBeEmpty()
            ->and($werkzeug['description'])->not->toBeEmpty()
            ->and($werkzeug['inputSchema'])->toHaveKey('properties');
    }
});

it('kennzeichnet die löschenden Werkzeuge als destruktiv', function (): void {
    $user = User::factory()->create();

    $antwort = $this->withHeader('Authorization', 'Bearer '.$user->createToken('Test')->plainTextToken)
        ->postJson(route('mcp.portal'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertOk();

    $destruktiv = collect($antwort->json('result.tools'))
        ->filter(fn (array $werkzeug): bool => $werkzeug['annotations']['destructiveHint'] ?? false)
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($destruktiv)->toBe([
        'ansprechpartner-loeschen',
        'kunde-loeschen',
        'leistung-loeschen',
        'preis-direkt-setzen',
        'produkt-loeschen',
        'projekt-loeschen',
    ]);
});

it('findet Kunden über die Kundennummer', function (): void {
    $kunde = Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);

    $antwort = PortalServer::actingAs(User::factory()->create())
        ->tool(KundenSuchen::class, ['suchbegriff' => $kunde->customer_number]);

    $antwort->assertOk()->assertSee('Nordlicht Medien');
});
