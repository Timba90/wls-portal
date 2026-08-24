<?php

namespace App\Actions\Services;

use App\Enums\BillingIntervalUnit;
use App\Models\Category;
use App\Models\CustomerService;
use App\Support\BillingInterval;
use App\Support\Money;

/**
 * Stellt drei Staende einer Kundenleistung gegenueber.
 *
 * - **Stand** — der Katalog, wie ihn zuletzt jemand gesehen und entschieden
 *   hat; anfangs der Verknuepfungszeitpunkt (AE-6).
 * - **Katalog heute** — was im Katalog derzeit steht.
 * - **Leistung** — was beim Kunden tatsaechlich gilt.
 *
 * Aus dem Vergleich ergeben sich zwei verschiedene Aussagen, die die
 * Oberflaeche nicht vermischen darf: „der Katalog hat sich seither geaendert"
 * (Stand ungleich Katalog heute) und „der Kunde weicht bewusst ab" (Stand
 * ungleich Leistung). Erst beides zusammen erlaubt eine informierte
 * Entscheidung.
 *
 * @phpstan-type Vergleich array{
 *     feld: string,
 *     label: string,
 *     stand: string,
 *     katalog: string,
 *     leistung: string,
 *     katalogGeaendert: bool,
 *     kundeWeichtAb: bool,
 *     uebernehmbar: bool,
 * }
 */
class CompareWithCatalog
{
    public function __construct(private readonly BuildCatalogSnapshot $buildCatalogSnapshot) {}

    /** @var array<int|string, ?string> */
    private array $kategorienamen = [];

    /**
     * @return array<int, Vergleich>
     */
    public function __invoke(CustomerService $service): array
    {
        return array_map(
            fn (array $zeile): array => [
                ...array_diff_key($zeile, ['format' => null]),
                'stand' => ($zeile['format'])($zeile['stand']),
                'katalog' => ($zeile['format'])($zeile['katalog']),
                'leistung' => ($zeile['format'])($zeile['leistung']),
            ],
            $this->rawRows($service),
        );
    }

    /**
     * Gibt es etwas zu entscheiden? Nur Katalogaenderungen zaehlen — eine
     * bewusste Kundenabweichung allein ist kein offener Vorgang.
     *
     * Arbeitet bewusst auf den Rohwerten: die Frage ist ein Ja oder Nein, und
     * die Anzeigetexte kosten Kategorieabfragen, die dafuer niemand braucht.
     */
    public function hasOpenChanges(CustomerService $service): bool
    {
        foreach ($this->rawRows($service) as $zeile) {
            if ($zeile['katalogGeaendert']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vergleichszeilen mit Rohwerten und je einer Formatierungsfunktion.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rawRows(CustomerService $service): array
    {
        $stand = $service->catalogBaseline();
        $heute = ($this->buildCatalogSnapshot)($service->product, $service->productVariant);

        // Ohne Katalogherkunft gibt es nichts zu vergleichen — eine frei
        // erfasste Leistung weicht von nichts ab.
        if (blank($stand) || blank($heute)) {
            return [];
        }

        return array_values(array_filter([
            $this->preis($service, $stand, $heute, 'sales_price_cents', 'Verkaufspreis', $service->sales_price_cents),
            $this->preis($service, $stand, $heute, 'purchase_price_cents', 'Einkaufspreis', $service->purchase_price_cents),
            $this->intervall($service, $stand, $heute),
            $this->kategorie($service, $stand, $heute),
            $this->bezeichnung($service, $stand, $heute),
        ]));
    }

    /**
     * @param  array<string, mixed>  $stand
     * @param  array<string, mixed>  $heute
     * @return Vergleich|null
     */
    private function preis(
        CustomerService $service,
        array $stand,
        array $heute,
        string $schluessel,
        string $label,
        int $wertDerLeistung,
    ): ?array {
        return $this->zeile(
            feld: $schluessel,
            label: $label,
            stand: $stand[$schluessel] ?? null,
            katalog: $heute[$schluessel] ?? null,
            leistung: $wertDerLeistung,
            anzeigen: fn (mixed $cents): string => Money::fromCents((int) $cents)->format(),
            uebernehmbar: ! $service->isArchived(),
        );
    }

    /**
     * @param  array<string, mixed>  $stand
     * @param  array<string, mixed>  $heute
     * @return Vergleich|null
     */
    private function intervall(CustomerService $service, array $stand, array $heute): ?array
    {
        return $this->zeile(
            feld: 'billing_interval',
            label: 'Abrechnungsintervall',
            stand: $this->intervallSchluessel($stand),
            katalog: $this->intervallSchluessel($heute),
            leistung: $service->billingInterval()->unit->value.':'.($service->billing_interval_count ?? ''),
            anzeigen: fn (mixed $wert): string => $this->intervallLabel((string) $wert),
            uebernehmbar: ! $service->isArchived(),
        );
    }

    /**
     * @param  array<string, mixed>  $stand
     * @param  array<string, mixed>  $heute
     * @return Vergleich|null
     */
    private function kategorie(CustomerService $service, array $stand, array $heute): ?array
    {
        return $this->zeile(
            feld: 'category',
            label: 'Kategorie',
            stand: ($stand['category_id'] ?? '').':'.($stand['subcategory_id'] ?? ''),
            katalog: ($heute['category_id'] ?? '').':'.($heute['subcategory_id'] ?? ''),
            leistung: ($service->category_id ?? '').':'.($service->subcategory_id ?? ''),
            anzeigen: fn (mixed $wert): string => $this->kategorieLabel((string) $wert),
            uebernehmbar: ! $service->isArchived(),
        );
    }

    /**
     * Der Name des Katalogartikels. Die Leistung traegt bewusst einen eigenen
     * Namen, deshalb steht hier nur, dass der Artikel jetzt anders heisst —
     * uebernehmen laesst er sich nicht.
     *
     * @param  array<string, mixed>  $stand
     * @param  array<string, mixed>  $heute
     * @return Vergleich|null
     */
    private function bezeichnung(CustomerService $service, array $stand, array $heute): ?array
    {
        return $this->zeile(
            feld: 'product_name',
            label: 'Bezeichnung im Katalog',
            stand: $stand['product_name'] ?? null,
            katalog: $heute['product_name'] ?? null,
            leistung: $service->name,
            anzeigen: fn (mixed $wert): string => (string) $wert,
            uebernehmbar: false,
            nurBeiKatalogaenderung: true,
        );
    }

    /**
     * @param  callable(mixed): string  $anzeigen
     * @return Vergleich|null
     */
    private function zeile(
        string $feld,
        string $label,
        mixed $stand,
        mixed $katalog,
        mixed $leistung,
        callable $anzeigen,
        bool $uebernehmbar,
        bool $nurBeiKatalogaenderung = false,
    ): ?array {
        if ($stand === null || $katalog === null) {
            return null;
        }

        $katalogGeaendert = $stand !== $katalog;
        $kundeWeichtAb = $stand !== $leistung;

        // Zeilen ohne Unterschied gehoeren nicht in die Gegenueberstellung.
        if (! $katalogGeaendert && ! $kundeWeichtAb) {
            return null;
        }

        if ($nurBeiKatalogaenderung && ! $katalogGeaendert) {
            return null;
        }

        return [
            'feld' => $feld,
            'label' => $label,
            'stand' => $stand,
            'katalog' => $katalog,
            'leistung' => $leistung,
            'katalogGeaendert' => $katalogGeaendert,
            'kundeWeichtAb' => $kundeWeichtAb,
            // Was sich nicht geaendert hat, muss auch nicht uebernommen werden.
            'uebernehmbar' => $uebernehmbar && $katalogGeaendert,
            'format' => $anzeigen,
        ];
    }

    /**
     * @param  array<string, mixed>  $werte
     */
    private function intervallSchluessel(array $werte): ?string
    {
        if (! isset($werte['billing_interval_unit'])) {
            return null;
        }

        return $werte['billing_interval_unit'].':'.($werte['billing_interval_count'] ?? '');
    }

    private function intervallLabel(string $schluessel): string
    {
        [$einheit, $anzahl] = array_pad(explode(':', $schluessel, 2), 2, '');

        return BillingInterval::make(
            BillingIntervalUnit::from($einheit),
            $anzahl === '' ? null : (int) $anzahl,
        )->label();
    }

    private function kategorieLabel(string $schluessel): string
    {
        [$kategorie, $unterkategorie] = array_pad(explode(':', $schluessel, 2), 2, '');

        $namen = collect([$kategorie, $unterkategorie])
            ->filter(fn (string $id): bool => $id !== '')
            ->map(fn (string $id): ?string => $this->kategorienamen[$id] ??= Category::query()->find($id)?->name)
            ->filter();

        return $namen->isEmpty() ? '—' : $namen->implode(' › ');
    }
}
