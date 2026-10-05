<?php

namespace App\Support\Flux;

use App\Enums\FluxLedgerAccountType;
use App\Models\IntegrationCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FluxClient
{
    private array $credentials;

    public function __construct(?array $credentials = null)
    {
        $this->credentials = $credentials ?? IntegrationCredential::valuesFor('flux');
        if ($this->credentials !== []) {
            self::validateCredentials($this->credentials);
        }
    }

    public static function validateCredentials(array $credentials): void
    {
        $url = parse_url($credentials['base_url'] ?? '');
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
            || ($url['host'] ?? '') !== config('services.flux.allowed_host')
            || rtrim($url['path'] ?? '', '/') !== '/api'
            || isset($url['user'], $url['pass']) || isset($url['user'])
            || isset($url['query']) || isset($url['fragment']) || isset($url['port'])
            || ! is_string($credentials['token'] ?? null) || trim($credentials['token']) === ''
            || strlen($credentials['token']) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $credentials['token'])
            || ! filter_var($credentials['tenant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new FluxException('Flux-Adresse, API-Token und positive Mandanten-ID müssen vollständig und gültig sein.');
        }
    }

    public function isConfigured(): bool
    {
        return $this->credentials !== [];
    }

    public function tenantId(): int
    {
        return (int) ($this->credentials['tenant_id'] ?? 0);
    }

    public function testConnection(): string
    {
        $this->request('GET', 'auth/token/validate');
        $this->ledgerAccounts();

        return 'Flux antwortet; der Sachkontenzugriff funktioniert.';
    }

    public function ledgerAccounts(?float $deadline = null): array
    {
        $accounts = [];
        $lastPage = null;
        $seen = [];
        for ($page = 1; $page <= 1000; $page++) {
            $this->checkDeadline($deadline);
            $body = $this->request('GET', 'ledger-accounts', ['page' => $page, 'per_page' => 100]);
            $this->checkDeadline($deadline);
            $data = $body['data'] ?? null;
            if (! is_array($data) || ! is_array($data['data'] ?? null)
                || ($data['current_page'] ?? null) !== $page
                || ! is_int($data['last_page'] ?? null) || $data['last_page'] < $page
                || ($lastPage !== null && $lastPage !== $data['last_page'])) {
                throw new FluxException('Flux hat eine unvollständige oder ungültige Kontenliste geliefert.');
            }
            $lastPage = $data['last_page'];
            foreach ($data['data'] as $account) {
                $this->validateAccount($account);
                if (isset($seen[$account['id']])) {
                    throw new FluxException('Flux hat wiederholte Konten in der Seitennavigation geliefert.');
                }
                $seen[$account['id']] = true;
                $accounts[] = $account;
            }
            if ($page === $lastPage) {
                return $accounts;
            }
        }
        throw new FluxException('Die Flux-Kontenliste überschreitet die maximale Seitenzahl.');
    }

    private function checkDeadline(?float $deadline): void
    {
        if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
            throw new FluxException('Der Kontenabgleich dauert zu lange. Es wurde kein weiterer Anlageversuch ausgeführt.');
        }
    }

    public function createLedgerAccount(array $payload): array
    {
        $payload['tenant_id'] = $this->tenantId();
        $body = $this->request('POST', 'ledger-accounts', $payload);
        $account = $body['data'] ?? null;
        $this->validateAccount($account);
        if ((int) $account['tenant_id'] !== $this->tenantId()
            || (string) $account['number'] !== (string) $payload['number']
            || $account['ledger_account_type_enum'] !== $payload['ledger_account_type_enum']) {
            throw new FluxException('Die angelegte Flux-Kontozuordnung konnte nicht bestätigt werden.');
        }

        return $account;
    }

    private function validateAccount(mixed $account): void
    {
        if (! is_array($account) || ! is_int($account['id'] ?? null) || $account['id'] < 1
            || ! is_int($account['tenant_id'] ?? null) || $account['tenant_id'] < 1
            || (! is_string($account['number'] ?? null) && ! is_int($account['number'] ?? null))
            || ! preg_match('/^\d+$/', (string) ($account['number'] ?? ''))
            || ! is_string($account['name'] ?? null) || $account['name'] === ''
            || ! is_string($account['ledger_account_type_enum'] ?? null)
            || FluxLedgerAccountType::tryFrom($account['ledger_account_type_enum']) === null
            || ! is_bool($account['is_automatic'] ?? null)) {
            throw new FluxException('Flux hat ungültige Sachkontendaten geliefert.');
        }
    }

    private function request(string $method, string $path, array $data = []): array
    {
        if (! $this->isConfigured()) {
            throw new FluxException('Bitte zuerst den Flux-Zugang einrichten.');
        }
        try {
            $response = Http::acceptJson()->withToken($this->credentials['token'])
                ->connectTimeout(5)->timeout(20)->withoutRedirecting()
                ->send($method, rtrim($this->credentials['base_url'], '/').'/'.$path,
                    [$method === 'GET' ? 'query' : 'json' => $data]);
        } catch (ConnectionException) {
            throw new FluxException('Die Flux-Verbindung ist fehlgeschlagen. Bei einer Anlage bitte erst den Bestand abgleichen.');
        }
        if (! $response->successful()) {
            throw new FluxException(match ($response->status()) {
                401, 403 => 'Flux-Zugang oder Berechtigung wurde abgelehnt.',
                422 => 'Flux hat die Kontendaten abgelehnt. Bitte Eingaben und Mandanten prüfen.',
                429 => 'Flux begrenzt die Anfragen. Bitte später erneut versuchen.',
                default => 'Flux hat keine erfolgreiche Antwort geliefert.',
            });
        }
        $body = $response->json();
        if (! is_array($body) || (isset($body['status']) && (! is_int($body['status']) || $body['status'] >= 300))) {
            throw new FluxException('Flux hat eine ungültige Antwort geliefert.');
        }

        return $body;
    }
}
