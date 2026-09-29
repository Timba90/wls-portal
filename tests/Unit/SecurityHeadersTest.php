<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

it('erlaubt nur beim geprueften OAuth-Formular die konkrete Rueckleitung', function (string $viewName, int $status, mixed $redirect, string $expected): void {
    $request = Request::create('/oauth/authorize', 'GET', ['redirect_uri' => $redirect]);
    $view = Mockery::mock(View::class);
    $view->shouldReceive('render')->andReturn('Zugriff erlauben?');
    $view->shouldReceive('name')->andReturn($viewName);
    $view->shouldReceive('getData')->andReturn(['request' => $request]);

    $response = (new SecurityHeaders)->handle(
        $request,
        fn (): Response => new Response($view, $status),
    );

    $policy = $response->headers->get('Content-Security-Policy');

    expect(explode('; ', $policy))->toContain($expected)
        ->and($policy)->toContain("default-src 'self'", "object-src 'none'", "frame-ancestors 'none'");
})->with([
    'Codex mit festem Port' => ['oauth.authorize', 200, 'http://127.0.0.1:62789/callback/test', "form-action 'self' http://127.0.0.1:62789"],
    'Web-Client' => ['oauth.authorize', 200, 'https://chatgpt.com/callback?state=test', "form-action 'self' https://chatgpt.com"],
    'Andere Seite' => ['auth.login', 200, 'https://untrusted.example/callback', "form-action 'self'"],
    'Fehlerantwort' => ['oauth.authorize', 403, 'https://untrusted.example/callback', "form-action 'self'"],
    'Fehlende Adresse' => ['oauth.authorize', 200, null, "form-action 'self'"],
    'Ungueltige Struktur' => ['oauth.authorize', 200, ['https://untrusted.example'], "form-action 'self'"],
    'Keine Header-Injektion' => ['oauth.authorize', 200, 'https://evil.example;script-src/callback', "form-action 'self'"],
]);

it('uebernimmt keine Rueckleitung aus einer beliebigen Anfrage', function (): void {
    $request = Request::create('/login', 'GET', ['redirect_uri' => 'https://untrusted.example']);
    $response = (new SecurityHeaders)->handle($request, fn (): Response => new Response('Login'));

    expect(explode('; ', $response->headers->get('Content-Security-Policy')))
        ->toContain("form-action 'self'");
});
