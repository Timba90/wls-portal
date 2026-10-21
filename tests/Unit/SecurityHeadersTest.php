<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('erlaubt nur die Herkunft der OAuth Rueckleitung im Formularziel', function (string $uri, string $origin): void {
    $request = Request::create('/oauth/authorize', 'GET', ['redirect_uri' => $uri]);
    $response = new Response;
    $response->original = view('oauth.authorize', ['request' => $request]);

    $result = (new SecurityHeaders)->handle($request, fn () => $response);

    expect($result->headers->get('Content-Security-Policy'))
        ->toContain("form-action 'self' ".$origin)
        ->not->toContain('/callback');
})->with([
    ['https://client.test/callback?state=secret', 'https://client.test'],
    ['http://127.0.0.1:8123/callback', 'http://127.0.0.1:8123'],
    ['http://[::1]:8123/callback', 'http://[::1]:8123'],
]);

it('erweitert die Formularziele nicht fuer ungueltige Adressen', function (mixed $uri): void {
    $request = Request::create('/oauth/authorize', 'GET', ['redirect_uri' => $uri]);
    $response = new Response;
    $response->original = view('oauth.authorize', ['request' => $request]);

    $result = (new SecurityHeaders)->handle($request, fn () => $response);

    expect(explode('; ', $result->headers->get('Content-Security-Policy')))
        ->toContain("form-action 'self'");
})->with([
    [null],
    [['https://client.test/callback']],
    ['javascript:alert(1)'],
    ['//client.test/callback'],
    ['https://bad%20host.test/callback'],
]);

it('begrenzt die Ausnahme auf erfolgreiche OAuth Views', function (string $type, int $status): void {
    $request = Request::create('/login', 'GET', ['redirect_uri' => 'https://client.test/callback']);
    $response = $type === 'json' ? response()->json(['ok' => true], $status) : new Response('', $status);

    if ($type !== 'json' && $type !== 'text') {
        $response->original = view($type, ['request' => $request]);
    }

    $result = (new SecurityHeaders)->handle($request, fn () => $response);

    expect(explode('; ', $result->headers->get('Content-Security-Policy')))
        ->toContain("form-action 'self'");
})->with([
    ['auth.login', 200],
    ['oauth.authorize', 400],
    ['oauth.authorize', 302],
    ['json', 200],
    ['text', 200],
]);
