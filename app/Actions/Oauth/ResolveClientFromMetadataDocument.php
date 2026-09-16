<?php

namespace App\Actions\Oauth;

use App\Models\OauthClientDocument;
use App\Support\Oauth\ClientIdMetadataException;
use App\Support\Oauth\ClientIdMetadataFetcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Client;
use Ramsey\Uuid\Uuid;

/**
 * Macht aus der Adresse, die ein Client als Kennung schickt, einen Client,
 * mit dem Passport arbeiten kann.
 *
 * Das Dokument wird geholt, geprueft und auf einen lokalen Datensatz
 * abgebildet. Dessen Kennung leitet sich fest aus der Adresse ab, damit
 * derselbe Client bei jedem Mal denselben Datensatz trifft — und damit die
 * Zustimmung, die ein Benutzer einmal gegeben hat, beim naechsten Mal noch
 * gilt.
 */
class ResolveClientFromMetadataDocument
{
    public function __construct(private readonly ClientIdMetadataFetcher $fetcher) {}

    /**
     * Gibt null zurueck, wenn die Kennung keine verwendbare Adresse ist oder
     * das Dokument nicht taugt. Der Grund steht im Protokoll: fuer den
     * anfragenden Client bleibt es bei „unbekannter Client", damit sich ueber
     * die Fehlermeldung nichts ueber uns erfahren laesst.
     */
    public function __invoke(string $clientId): ?Client
    {
        if (! config('portal.mcp.oauth.client_documents.enabled')) {
            return null;
        }

        if (! ClientIdMetadataFetcher::looksLikeUrl($clientId)) {
            return null;
        }

        $bekannt = OauthClientDocument::query()->where('url', $clientId)->first();

        if ($bekannt !== null && $bekannt->isFresh()) {
            return $bekannt->client;
        }

        try {
            $dokument = $this->fetcher->fetch($clientId);
        } catch (ClientIdMetadataException $ausnahme) {
            Log::warning('Client-Dokument abgelehnt.', [
                'client_id' => $clientId,
                'grund' => $ausnahme->getMessage(),
            ]);

            // Ein Aussetzer beim Client soll keine laufende Verbindung
            // abreissen, solange wir einen kuerzlich geprueften Stand haben.
            return $bekannt?->isWithinGracePeriod() === true ? $bekannt->client : null;
        }

        return $this->store($clientId, $dokument, $bekannt);
    }

    /**
     * @param  array{client_id: string, client_name: string, redirect_uris: list<string>, grant_types: list<string>}  $dokument
     */
    private function store(string $clientId, array $dokument, ?OauthClientDocument $bekannt): Client
    {
        $kennung = self::clientKey($clientId);

        return DB::transaction(function () use ($clientId, $dokument, $bekannt, $kennung): Client {
            $client = Client::query()->find($kennung);
            $neu = $client === null;

            $client = Client::query()->updateOrCreate(['id' => $kennung], [
                'name' => $dokument['client_name'],
                'secret' => null,
                'provider' => null,
                'redirect_uris' => $dokument['redirect_uris'],
                'grant_types' => $dokument['grant_types'],
                'revoked' => false,
            ]);

            $minuten = (int) config('portal.mcp.oauth.client_documents.cache_minutes');

            OauthClientDocument::query()->updateOrCreate(['url' => $clientId], [
                'client_id' => $client->getKey(),
                'document' => $dokument,
                'fetched_at' => now(),
                'refresh_after' => now()->addMinutes($minuten),
            ]);

            $geaendert = $bekannt !== null && $bekannt->document !== $dokument;

            if ($neu || $geaendert) {
                $this->record($clientId, $dokument, $neu);
            }

            return $client;
        });
    }

    /**
     * Ein neuer oder geaenderter Client gehoert ins Protokoll: er kann danach
     * im Namen von Benutzern handeln, und wer das war, muss sich nachlesen
     * lassen. Der Datensatz in `oauth_client_documents` haelt dazu das
     * Dokument fest, auf dessen Grundlage er Zugang bekommen hat.
     *
     * @param  array{client_id: string, client_name: string, redirect_uris: list<string>, grant_types: list<string>}  $dokument
     */
    private function record(string $clientId, array $dokument, bool $neu): void
    {
        Log::info($neu ? 'Neuer OAuth-Client über sein Metadatendokument.' : 'OAuth-Client hat sein Metadatendokument geändert.', [
            'client_id' => $clientId,
            'client_name' => $dokument['client_name'],
            'redirect_uris' => $dokument['redirect_uris'],
        ]);
    }

    /**
     * Die Kennung des lokalen Datensatzes, fest abgeleitet aus der Adresse.
     */
    public static function clientKey(string $clientId): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, $clientId)->toString();
    }
}
