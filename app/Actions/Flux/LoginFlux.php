<?php

namespace App\Actions\Flux;

use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class LoginFlux
{
    public function handle(array $connection, array $login, int $userId): void
    {
        // Validate the destination and tenant before transmitting a password.
        $connection = array_intersect_key($connection, array_flip(['base_url', 'tenant_id']));
        FluxClient::validateCredentials(array_merge($connection, ['token' => 'validation-placeholder']));
        $username = $login['username'] ?? null;
        $password = $login['password'] ?? null;
        if (! is_string($username) || trim($username) === '' || strlen($username) > 255
            || ! is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new FluxException('Bitte Flux-E-Mail und Passwort vollständig eingeben.');
        }
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(20)->withoutRedirecting()
                ->post(rtrim($connection['base_url'], '/').'/auth/token', ['username' => trim($username), 'password' => $password]);
        } catch (ConnectionException) {
            throw new FluxException('Flux ist nicht erreichbar. Die Anmeldung wurde nicht wiederholt.');
        }
        if (! $response->successful()) {
            throw new FluxException(match ($response->status()) {
                401, 403 => 'Flux hat die Anmeldung abgelehnt. Bitte E-Mail, Passwort und Benutzerfreigabe prüfen.',
                429 => 'Flux begrenzt die Anmeldungen. Bitte später erneut versuchen.',
                default => 'Flux hat keine erfolgreiche Anmeldeantwort geliefert.',
            });
        }
        $body = $response->json();
        $token = is_array($body) ? ($body['access_token'] ?? $body['token'] ?? null) : null;
        if (! is_array($body) || (isset($body['status']) && $body['status'] !== 200)
            || ! is_string($token) || trim($token) === '') {
            throw new FluxException('Flux hat kein gültiges API-Token geliefert.');
        }
        app(SaveFluxCredentials::class)->handle(array_merge($connection, ['token' => $token]), $userId);
    }
}
