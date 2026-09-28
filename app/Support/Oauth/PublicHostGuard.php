<?php

namespace App\Support\Oauth;

/**
 * Schuetzt davor, dass wir im Auftrag eines Fremden Adressen im eigenen Netz
 * abrufen (SSRF).
 *
 * Ein Client-ID-Metadatendokument wird von einer Adresse geholt, die uns der
 * Client nennt. Zeigte sie auf 127.0.0.1, auf das Metadaten-Interface der
 * Cloud oder in unser internes Netz, waere unser Server das Werkzeug, mit dem
 * jemand dort hineinschaut. Die Spezifikation verlangt deshalb ausdruecklich,
 * Adressen mit besonderer Verwendung abzuweisen.
 */
class PublicHostGuard
{
    /**
     * @return list<string> die geprueften Adressen, an die die Anfrage
     *                      danach gebunden wird
     *
     * @throws ClientIdMetadataException
     */
    public function assertPublic(string $host): array
    {
        $adressen = $this->resolve($host);

        if ($adressen === []) {
            throw new ClientIdMetadataException(
                sprintf('Der Name %s laesst sich nicht aufloesen.', $host)
            );
        }

        foreach ($adressen as $adresse) {
            if (! self::isPublic($adresse)) {
                throw new ClientIdMetadataException(
                    sprintf('%s zeigt auf %s und damit nicht ins oeffentliche Netz.', $host, $adresse)
                );
            }
        }

        return $adressen;
    }

    /**
     * Oeffentlich ist, was weder privat noch reserviert ist.
     *
     * Die beiden Filter von PHP decken die Bereiche aus RFC 1918, RFC 4193,
     * Loopback, Link-Local und die reservierten Bloecke ab — einschliesslich
     * 169.254.169.254, ueber das Cloud-Anbieter ihre Metadaten ausliefern.
     */
    public static function isPublic(string $adresse): bool
    {
        return filter_var(
            $adresse,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $eintraege = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($eintraege === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $eintrag): ?string => $eintrag['ip'] ?? $eintrag['ipv6'] ?? null,
            $eintraege
        )));
    }
}
