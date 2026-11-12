<?php

namespace App\Support\Lexoffice;

use App\Models\IntegrationCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Lesender Anschluss an die Lexoffice-API (§57).
 *
 * Schreibende Aufrufe gibt es bewusst nicht. Lexoffice erlaubt zwei Anfragen
 * pro Sekunde; der Anschluss haelt zwischen zwei Anfragen den konfigurierten
 * Abstand ein und wartet bei 429 einmal kurz, bevor er aufgibt.
 */
class LexofficeClient
{
    private const int PAGE_SIZE = 250;

    private const int MAX_PAGES = 100;

    private ?float $lastRequestAt = null;

    public function __construct(private ?string $apiKey = null)
    {
        $this->apiKey ??= IntegrationCredential::valuesFor('lexoffice')['api_key']
            ?? config('services.lexoffice.api_key');
    }

    public function isConfigured(): bool
    {
        return is_string($this->apiKey) && trim($this->apiKey) !== '';
    }

    /**
     * Prueft den Zugang, ohne etwas zu veraendern.
     */
    public function testConnection(): string
    {
        $profile = $this->get('profile');

        return sprintf('Lexoffice antwortet für „%s“.', $profile['companyName'] ?? 'unbekannte Firma');
    }

    /**
     * Alle Serienrechnungsvorlagen als Zusammenfassung, ohne Positionen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recurringTemplates(): array
    {
        $templates = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $body = $this->get('recurring-templates', [
                'page' => $page,
                'size' => self::PAGE_SIZE,
                'sort' => 'createdDate,ASC',
            ]);

            if (! is_array($body['content'] ?? null)) {
                throw new LexofficeException('Lexoffice hat eine ungültige Liste der Serienrechnungen geliefert.');
            }

            foreach ($body['content'] as $template) {
                if (! is_array($template) || ! is_string($template['id'] ?? null)) {
                    throw new LexofficeException('Lexoffice hat eine Serienrechnung ohne Kennung geliefert.');
                }

                $templates[] = $template;
            }

            if (($body['last'] ?? true) === true) {
                return $templates;
            }
        }

        throw new LexofficeException('Die Liste der Serienrechnungen überschreitet die maximale Seitenzahl.');
    }

    /**
     * Eine Serienrechnungsvorlage mit Positionen.
     *
     * @return array<string, mixed>
     */
    public function recurringTemplate(string $id): array
    {
        $template = $this->get('recurring-templates/'.rawurlencode($id));

        if (($template['id'] ?? null) !== $id || ! is_array($template['lineItems'] ?? null)) {
            throw new LexofficeException("Lexoffice hat die Serienrechnung {$id} unvollständig geliefert.");
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        if (! $this->isConfigured()) {
            throw new LexofficeException('Es ist kein Lexoffice-API-Schlüssel hinterlegt.');
        }

        $response = $this->send($path, $query);

        if ($response->status() === 429) {
            usleep(1_500_000);
            $response = $this->send($path, $query);
        }

        if (! $response->successful()) {
            throw new LexofficeException(match ($response->status()) {
                401, 403 => 'Lexoffice hat den API-Schlüssel abgelehnt.',
                404 => 'Lexoffice kennt den angefragten Datensatz nicht.',
                429 => 'Lexoffice begrenzt die Anfragen. Bitte später erneut versuchen.',
                default => "Lexoffice antwortet mit Status {$response->status()}.",
            });
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new LexofficeException('Lexoffice hat keine lesbare Antwort geliefert.');
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(string $path, array $query): Response
    {
        $this->throttle();

        try {
            return Http::baseUrl(rtrim((string) config('services.lexoffice.base_url'), '/'))
                ->withToken((string) $this->apiKey)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->get($path, $query);
        } catch (ConnectionException) {
            throw new LexofficeException('Lexoffice ist nicht erreichbar.');
        } finally {
            $this->lastRequestAt = microtime(true);
        }
    }

    private function throttle(): void
    {
        $interval = (int) config('services.lexoffice.request_interval_ms', 550) / 1000;

        if ($this->lastRequestAt === null || $interval <= 0) {
            return;
        }

        $wait = $this->lastRequestAt + $interval - microtime(true);

        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
    }
}
