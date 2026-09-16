<?php

namespace App\Passport;

use App\Actions\Oauth\ResolveClientFromMetadataDocument;
use App\Support\Oauth\ClientIdMetadataFetcher;
use Laravel\Passport\Bridge\ClientRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * Laesst neben den von Hand angelegten Clients auch solche zu, die sich ueber
 * ein Metadatendokument ausweisen.
 *
 * Passport speichert Kennungen als UUID und haengt Autorisierungscodes und
 * Tokens daran. Nach aussen tritt der Client mit seiner Adresse auf, nach
 * innen mit der daraus abgeleiteten UUID — der Tausch passiert hier, und
 * dadurch bleibt der ganze Rest von Passport unveraendert.
 */
class ClientIdMetadataClientRepository extends ClientRepository
{
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        if (! ClientIdMetadataFetcher::looksLikeUrl($clientIdentifier)) {
            return parent::getClientEntity($clientIdentifier);
        }

        $client = app(ResolveClientFromMetadataDocument::class)($clientIdentifier);

        return $client === null ? null : $this->fromClientModel($client);
    }

    /**
     * Ein Client mit Metadatendokument hat kein Geheimnis.
     *
     * League ruft diese Pruefung nur fuer vertrauliche Clients auf; wird sie
     * hier doch erreicht, gibt jemand einen oeffentlichen Client als
     * vertraulich aus, und das ist keine Anmeldung.
     */
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        if (ClientIdMetadataFetcher::looksLikeUrl($clientIdentifier)) {
            return false;
        }

        return parent::validateClient($clientIdentifier, $clientSecret, $grantType);
    }
}
