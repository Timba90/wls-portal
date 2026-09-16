<?php

namespace App\Support\Oauth;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Holt und prueft das Metadatendokument eines Clients.
 *
 * Der Client nennt als `client_id` keine ausgedachte Kennung, sondern eine
 * HTTPS-Adresse, unter der er beschreibt, wer er ist und wohin er
 * zurueckgeleitet werden will. Wir holen dieses Dokument und pruefen es. Das
 * ersetzt sowohl den offenen Registrierungsendpunkt als auch das Anlegen von
 * Hand: der Client weist sich dadurch aus, dass er eine Adresse kontrolliert.
 *
 * Nach draft-ietf-oauth-client-id-metadata-document. Die Regeln, die dort
 * MUST heissen, stehen hier als Pruefungen — und bewusst enger: wir nehmen
 * nur oeffentliche Clients, also solche ohne Geheimnis, und nur von Hosts,
 * die in der Einstellung stehen.
 */
class ClientIdMetadataFetcher
{
    public function __construct(private readonly PublicHostGuard $hostGuard) {}

    /**
     * @return array{client_id: string, client_name: string, redirect_uris: list<string>, grant_types: list<string>}
     *
     * @throws ClientIdMetadataException
     */
    public function fetch(string $clientId): array
    {
        $this->assertUsableUrl($clientId);

        $antwort = $this->request($clientId);

        return $this->assertUsableDocument($clientId, $antwort);
    }

    /**
     * Ob eine Kennung ueberhaupt als Adresse gemeint ist.
     *
     * Nur danach wird das Dokument geholt; alles andere bleibt ein Client,
     * der von Hand angelegt wurde.
     */
    public static function looksLikeUrl(string $clientId): bool
    {
        return str_starts_with($clientId, 'https://');
    }

    /**
     * @throws ClientIdMetadataException
     */
    private function assertUsableUrl(string $clientId): void
    {
        $teile = parse_url($clientId);

        if ($teile === false || ($teile['scheme'] ?? null) !== 'https') {
            throw new ClientIdMetadataException('Eine Client-Kennung muss eine HTTPS-Adresse sein.');
        }

        if (isset($teile['fragment'])) {
            throw new ClientIdMetadataException('Eine Client-Kennung darf keinen Anker enthalten.');
        }

        if (isset($teile['user']) || isset($teile['pass'])) {
            throw new ClientIdMetadataException('Eine Client-Kennung darf keine Zugangsdaten enthalten.');
        }

        $pfad = $teile['path'] ?? '';

        if ($pfad === '' || $pfad === '/') {
            throw new ClientIdMetadataException('Eine Client-Kennung braucht einen Pfad.');
        }

        $host = $teile['host'] ?? '';

        $this->assertAllowedHost($host);
        $this->hostGuard->assertPublic($host);
    }

    /**
     * Wessen Dokumente wir ueberhaupt holen.
     *
     * Ohne diese Liste koennte jeder im Netz einen Zustimmungsdialog in
     * unserem Portal ausloesen. Zustimmen muesste zwar immer noch ein
     * angemeldeter Benutzer — aber ein Dialog, den man gar nicht erst zu
     * Gesicht bekommt, kann auch niemanden taeuschen.
     *
     * @throws ClientIdMetadataException
     */
    private function assertAllowedHost(string $host): void
    {
        /** @var list<string> $erlaubt */
        $erlaubt = config('portal.mcp.oauth.client_documents.allowed_hosts');

        if ($erlaubt === []) {
            return;
        }

        foreach ($erlaubt as $eintrag) {
            if ($host === $eintrag || str_ends_with($host, '.'.$eintrag)) {
                return;
            }
        }

        throw new ClientIdMetadataException(
            sprintf('Von %s nehmen wir keine Client-Dokumente an.', $host)
        );
    }

    /**
     * @throws ClientIdMetadataException
     */
    private function request(string $clientId): string
    {
        $grenze = (int) config('portal.mcp.oauth.client_documents.max_bytes');

        try {
            $antwort = Http::withOptions([
                // Der Spezifikation nach darf einer Weiterleitung nicht
                // gefolgt werden: sonst entschiede der Client nach der
                // Host-Pruefung noch einmal neu, wo wir hinfassen.
                'allow_redirects' => false,
            ])
                ->timeout((int) config('portal.mcp.oauth.client_documents.timeout'))
                ->accept('application/json')
                ->get($clientId);
        } catch (Throwable $ausnahme) {
            throw new ClientIdMetadataException(
                sprintf('Das Client-Dokument war nicht erreichbar: %s', $ausnahme->getMessage()),
                previous: $ausnahme
            );
        }

        if ($antwort->status() !== 200) {
            throw new ClientIdMetadataException(
                sprintf('Das Client-Dokument antwortete mit %d statt 200.', $antwort->status())
            );
        }

        $koerper = $antwort->body();

        if (strlen($koerper) > $grenze) {
            throw new ClientIdMetadataException(
                sprintf('Das Client-Dokument ist groesser als %d Byte.', $grenze)
            );
        }

        return $koerper;
    }

    /**
     * @return array{client_id: string, client_name: string, redirect_uris: list<string>, grant_types: list<string>}
     *
     * @throws ClientIdMetadataException
     */
    private function assertUsableDocument(string $clientId, string $koerper): array
    {
        $dokument = json_decode($koerper, true);

        if (! is_array($dokument)) {
            throw new ClientIdMetadataException('Das Client-Dokument ist kein JSON-Objekt.');
        }

        // Zeichenweiser Vergleich, wie in der Spezifikation: sonst koennte ein
        // Dokument unter einer Adresse fuer eine andere sprechen.
        if (($dokument['client_id'] ?? null) !== $clientId) {
            throw new ClientIdMetadataException(
                'Das Client-Dokument nennt eine andere Kennung, als unter der es liegt.'
            );
        }

        $verfahren = $dokument['token_endpoint_auth_method'] ?? null;

        if ($verfahren !== 'none') {
            throw new ClientIdMetadataException(sprintf(
                'Wir nehmen nur oeffentliche Clients (token_endpoint_auth_method "none"), angegeben war %s.',
                $verfahren === null ? 'nichts' : '"'.$verfahren.'"'
            ));
        }

        $rueckleitungen = $this->assertUsableRedirectUris($dokument['redirect_uris'] ?? null);

        $arten = array_values(array_intersect(
            is_array($dokument['grant_types'] ?? null) ? $dokument['grant_types'] : [],
            ['authorization_code', 'refresh_token']
        ));

        if (! in_array('authorization_code', $arten, true)) {
            throw new ClientIdMetadataException(
                'Das Client-Dokument muss den Autorisierungscode-Fluss nennen.'
            );
        }

        $name = $dokument['client_name'] ?? null;

        return [
            'client_id' => $clientId,
            'client_name' => is_string($name) && trim($name) !== ''
                ? mb_substr(trim($name), 0, 120)
                : parse_url($clientId, PHP_URL_HOST),
            'redirect_uris' => $rueckleitungen,
            'grant_types' => $arten,
        ];
    }

    /**
     * @return list<string>
     *
     * @throws ClientIdMetadataException
     */
    private function assertUsableRedirectUris(mixed $rueckleitungen): array
    {
        if (! is_array($rueckleitungen) || $rueckleitungen === []) {
            throw new ClientIdMetadataException('Das Client-Dokument nennt keine Rueckleitungsadresse.');
        }

        foreach ($rueckleitungen as $adresse) {
            if (! is_string($adresse) || ! str_starts_with($adresse, 'https://')) {
                throw new ClientIdMetadataException('Rueckleitungsadressen muessen HTTPS sein.');
            }
        }

        return array_values($rueckleitungen);
    }
}
