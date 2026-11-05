<?php

namespace App\Support\Flux;

use App\Enums\FluxLedgerAccountType;
use App\Models\IntegrationCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
        if (! is_string($credentials['base_url'] ?? null)) {
            throw new FluxException('Bitte eine gültige Flux-Adresse eingeben.');
        }
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
        $validation = $this->request('GET', 'auth/token/validate');
        if (($validation['status'] ?? null) !== 'token valid') {
            throw new FluxException('Flux hat die Token-Gültigkeit nicht bestätigt.');
        }
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

    /**
     * Rechnungen eines Flux-Kontakts, neueste zuerst.
     *
     * Flux filtert serverseitig nach `contact_id`. Jeder Eintrag wird hier
     * trotzdem noch einmal gegen den Kontakt geprueft: ein ignorierter Filter
     * darf nie dazu fuehren, dass ein Kunde fremde Rechnungen sieht.
     *
     * @return list<array{id: int, invoice_number: string, invoice_date: ?string, payment_target_date: ?string, total_net_price: string, total_gross_price: string, balance: ?string, payment_state: ?string}>
     */
    public function invoices(int $contactId): array
    {
        $rechnungen = [];

        foreach ($this->paginate('orders', [
            'filter' => ['contact_id' => $contactId],
            'sort' => '-invoice_date',
        ]) as $order) {
            if (! is_array($order) || ! is_int($order['id'] ?? null)) {
                throw new FluxException('Flux hat ungültige Rechnungsdaten geliefert.');
            }

            if ((int) ($order['contact_id'] ?? 0) !== $contactId || blank($order['invoice_number'] ?? null)) {
                continue;
            }

            $rechnungen[] = [
                'id' => $order['id'],
                'invoice_number' => (string) $order['invoice_number'],
                'invoice_date' => $this->dateOrNull($order['invoice_date'] ?? null),
                'payment_target_date' => $this->dateOrNull($order['payment_target_date'] ?? null),
                'total_net_price' => (string) ($order['total_net_price'] ?? '0'),
                'total_gross_price' => (string) ($order['total_gross_price'] ?? '0'),
                'balance' => isset($order['balance']) ? (string) $order['balance'] : null,
                'payment_state' => is_string($order['payment_state'] ?? null) ? $order['payment_state'] : null,
            ];
        }

        usort($rechnungen, fn (array $a, array $b): int => [$b['invoice_date'], $b['id']] <=> [$a['invoice_date'], $a['id']]);

        return $rechnungen;
    }

    /**
     * PDF einer Rechnung — nur, wenn sie tatsaechlich zu diesem Kontakt gehoert.
     *
     * @return array{filename: string, content: string, mime_type: string}
     */
    public function invoicePdf(int $orderId, int $contactId): array
    {
        $order = $this->request('GET', 'orders/'.$orderId, ['include' => 'media'])['data'] ?? null;

        if (! is_array($order) || (int) ($order['id'] ?? 0) !== $orderId
            || (int) ($order['contact_id'] ?? 0) !== $contactId
            || blank($order['invoice_number'] ?? null)) {
            throw new FluxException('Die Rechnung wurde nicht gefunden.');
        }

        $media = collect($order['media'] ?? [])
            ->filter(fn (mixed $eintrag): bool => is_array($eintrag)
                && ($eintrag['collection_name'] ?? null) === 'invoice'
                && is_int($eintrag['id'] ?? null))
            ->sortByDesc('id')
            ->first();

        if ($media === null) {
            throw new FluxException('Zu dieser Rechnung liegt noch kein PDF vor.');
        }

        $response = $this->send('GET', 'media/private/'.$media['id']);
        $inhalt = $response->body();

        if (! str_starts_with($inhalt, '%PDF')) {
            throw new FluxException('Flux hat kein gültiges PDF geliefert.');
        }

        return [
            'filename' => 'Rechnung-'.preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $order['invoice_number']).'.pdf',
            'content' => $inhalt,
            'mime_type' => 'application/pdf',
        ];
    }

    /**
     * SEPA-Mandate eines Kontakts samt der zugehoerigen Bankverbindung.
     *
     * Die IBAN kommt nur maskiert zurueck; die Oberflaeche braucht nie mehr
     * als die letzten Stellen.
     *
     * @return list<array{id: int, mandate_reference_number: string, signed_date: ?string, type: string, account_holder: ?string, iban: ?string, bank_name: ?string}>
     */
    public function sepaMandates(int $contactId): array
    {
        $verbindungen = collect($this->paginate('contact-bank-connections', ['filter' => ['contact_id' => $contactId]]))
            ->filter(fn (mixed $verbindung): bool => is_array($verbindung) && (int) ($verbindung['contact_id'] ?? 0) === $contactId)
            ->keyBy('id');

        $mandate = [];

        foreach ($this->paginate('sepa-mandates', ['filter' => ['contact_id' => $contactId]]) as $mandat) {
            if (! is_array($mandat) || ! is_int($mandat['id'] ?? null) || (int) ($mandat['contact_id'] ?? 0) !== $contactId) {
                continue;
            }

            $verbindung = $verbindungen->get($mandat['contact_bank_connection_id'] ?? null);

            $mandate[] = [
                'id' => $mandat['id'],
                'mandate_reference_number' => (string) ($mandat['mandate_reference_number'] ?? ''),
                'signed_date' => $this->dateOrNull($mandat['signed_date'] ?? null),
                'type' => (string) ($mandat['sepa_mandate_type_enum'] ?? ''),
                'account_holder' => $verbindung['account_holder'] ?? null,
                'iban' => isset($verbindung['iban']) ? self::maskIban((string) $verbindung['iban']) : null,
                'bank_name' => $verbindung['bank_name'] ?? null,
            ];
        }

        usort($mandate, fn (array $a, array $b): int => $b['id'] <=> $a['id']);

        return $mandate;
    }

    /**
     * Legt Bankverbindung und SEPA-Mandat in Flux an.
     *
     * Zwei POSTs, keiner wird wiederholt. Scheitert das Mandat, bleibt die
     * Bankverbindung in Flux stehen — sie ist harmlos und wird beim naechsten
     * Versuch nicht doppelt angelegt, weil das Team das Mandat dann in Flux
     * nachzieht. Die Meldung sagt das.
     *
     * @param  array{account_holder: string, iban: string, bic?: ?string, bank_name?: ?string, signed_date: string, type: string}  $data
     * @return array{id: int, mandate_reference_number: string}
     */
    public function createSepaMandate(int $contactId, array $data): array
    {
        $verbindung = $this->request('POST', 'contact-bank-connections', array_filter([
            'contact_id' => $contactId,
            'account_holder' => $data['account_holder'],
            'iban' => $data['iban'],
            'bic' => $data['bic'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'is_credit_account' => false,
        ], fn (mixed $wert): bool => $wert !== null), 'Flux hat die Bankverbindung abgelehnt. Bitte IBAN und Kontoinhaber prüfen.')['data'] ?? null;

        if (! is_array($verbindung) || ! is_int($verbindung['id'] ?? null) || (int) ($verbindung['contact_id'] ?? 0) !== $contactId) {
            throw new FluxException('Die Bankverbindung konnte in Flux nicht bestätigt werden.');
        }

        try {
            $mandat = $this->request('POST', 'sepa-mandates', [
                'contact_id' => $contactId,
                'tenant_id' => $this->tenantId(),
                'contact_bank_connection_id' => $verbindung['id'],
                'sepa_mandate_type_enum' => $data['type'],
                'signed_date' => $data['signed_date'],
            ], 'Flux hat das SEPA-Mandat abgelehnt.')['data'] ?? null;
        } catch (FluxException $exception) {
            throw new FluxException($exception->getMessage().' Die Bankverbindung wurde bereits gespeichert; bitte nicht erneut absenden, wir melden uns.');
        }

        if (! is_array($mandat) || ! is_int($mandat['id'] ?? null) || (int) ($mandat['contact_id'] ?? 0) !== $contactId) {
            throw new FluxException('Das SEPA-Mandat konnte in Flux nicht bestätigt werden.');
        }

        return [
            'id' => $mandat['id'],
            'mandate_reference_number' => (string) ($mandat['mandate_reference_number'] ?? ''),
        ];
    }

    public static function maskIban(string $iban): string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        if (strlen($iban) <= 8) {
            return $iban;
        }

        return substr($iban, 0, 4).' **** **** '.substr($iban, -4);
    }

    /**
     * Alle Seiten eines Index-Endpunkts.
     *
     * @param  array<string, mixed>  $query
     * @return list<mixed>
     */
    private function paginate(string $path, array $query = []): array
    {
        $eintraege = [];
        $lastPage = null;

        for ($page = 1; $page <= 100; $page++) {
            $data = $this->request('GET', $path, [...$query, 'page' => $page, 'per_page' => 100])['data'] ?? null;

            if (! is_array($data) || ! is_array($data['data'] ?? null)
                || (int) ($data['current_page'] ?? 0) !== $page
                || ! is_int($data['last_page'] ?? null)
                || ($lastPage !== null && $lastPage !== $data['last_page'])) {
                throw new FluxException('Flux hat eine unvollständige oder ungültige Liste geliefert.');
            }

            $lastPage = $data['last_page'];
            array_push($eintraege, ...$data['data']);

            if ($page >= $lastPage) {
                return $eintraege;
            }
        }

        throw new FluxException('Die Flux-Liste überschreitet die maximale Seitenzahl.');
    }

    private function dateOrNull(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? substr($value, 0, 10) : null;
    }

    private function request(string $method, string $path, array $data = [], ?string $rejectedMessage = null): array
    {
        $response = $this->send($method, $path, $data, $rejectedMessage);
        $body = $response->json();
        $tokenValidation = $path === 'auth/token/validate' && ($body['status'] ?? null) === 'token valid';
        if (! is_array($body) || (! $tokenValidation && isset($body['status']) && (! is_int($body['status']) || $body['status'] < 200 || $body['status'] >= 300))) {
            throw new FluxException('Flux hat eine ungültige Antwort geliefert.');
        }

        return $body;
    }

    private function send(string $method, string $path, array $data = [], ?string $rejectedMessage = null): Response
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
                404 => 'Der Datensatz wurde in Flux nicht gefunden.',
                422 => $rejectedMessage ?? 'Flux hat die Kontendaten abgelehnt. Bitte Eingaben und Mandanten prüfen.',
                429 => 'Flux begrenzt die Anfragen. Bitte später erneut versuchen.',
                default => 'Flux hat keine erfolgreiche Antwort geliefert.',
            });
        }

        return $response;
    }
}
