<?php

namespace App\Actions\Lexoffice;

use App\Enums\LexofficeExecutionInterval;
use App\Models\LexofficeRecurringTemplate;
use App\Models\LexofficeRecurringTemplateItem;
use App\Support\Lexoffice\LexofficeClient;
use App\Support\Lexoffice\LexofficeException;
use App\Support\Lexoffice\MatchText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Liest alle Serienrechnungen aus Lexoffice und gleicht sie mit dem Portal ab.
 *
 * Positionen werden nur neu gelesen, wenn Lexoffice die Vorlage seit dem
 * letzten Lauf veraendert hat — die API erlaubt zwei Anfragen pro Sekunde,
 * und die meisten Vorlagen aendern sich nie. Danach werden Kunden verknuepft
 * und Positionen den Kundenleistungen zugeordnet, soweit das eindeutig geht.
 */
class SyncLexofficeRecurringTemplates
{
    public const string LastRunCacheKey = 'lexoffice:recurring-templates:last-run';

    public function __construct(
        private LinkLexofficeContacts $linkContacts,
        private MatchLexofficeTemplateItems $matchItems,
    ) {}

    /**
     * @return array{templates: int, fetched: int, removed: int, linked_customers: int, matched_items: int}
     */
    public function handle(?LexofficeClient $client = null): array
    {
        $client ??= app(LexofficeClient::class);
        $startedAt = now();

        try {
            $result = $this->run($client);
        } catch (Throwable $exception) {
            Cache::forever(self::LastRunCacheKey, [
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'error' => $exception instanceof LexofficeException
                    ? $exception->getMessage()
                    : 'Der Abgleich ist unerwartet abgebrochen.',
            ]);

            throw $exception;
        }

        Cache::forever(self::LastRunCacheKey, [
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'error' => null,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * @return array{templates: int, fetched: int, removed: int, linked_customers: int, matched_items: int}
     */
    private function run(LexofficeClient $client): array
    {
        $summaries = collect($client->recurringTemplates())->keyBy('id');
        $existing = LexofficeRecurringTemplate::query()->get()->keyBy('lexoffice_id');
        $fetched = 0;

        foreach ($summaries as $id => $summary) {
            /** @var LexofficeRecurringTemplate|null $template */
            $template = $existing->get($id);
            $remoteUpdatedAt = $this->timestamp($summary['updatedDate'] ?? null);
            $needsDetails = $template === null
                || $template->lexoffice_updated_at === null
                || $remoteUpdatedAt === null
                || ! $template->lexoffice_updated_at->equalTo($remoteUpdatedAt);

            $details = $needsDetails ? $client->recurringTemplate($id) : null;
            $fetched += $needsDetails ? 1 : 0;

            DB::transaction(function () use ($template, $summary, $details, $remoteUpdatedAt): void {
                $template = $this->storeTemplate($template ?? new LexofficeRecurringTemplate, $details ?? $summary, $remoteUpdatedAt);

                if ($details !== null) {
                    $this->storeItems($template, $details['lineItems']);
                }
            });
        }

        $removed = LexofficeRecurringTemplate::query()
            ->whereNotIn('lexoffice_id', $summaries->keys()->all())
            ->get()
            ->each->delete()
            ->count();

        return [
            'templates' => $summaries->count(),
            'fetched' => $fetched,
            'removed' => $removed,
            'linked_customers' => $this->linkContacts->handle(),
            'matched_items' => $this->matchItems->handle(),
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function storeTemplate(LexofficeRecurringTemplate $template, array $remote, ?Carbon $remoteUpdatedAt): LexofficeRecurringTemplate
    {
        $settings = $remote['recurringTemplateSettings'] ?? [];
        $interval = LexofficeExecutionInterval::tryFrom((string) ($settings['executionInterval'] ?? ''));

        if ($interval === null) {
            throw new LexofficeException(sprintf(
                'Die Serienrechnung für „%s" hat einen unbekannten Rhythmus.',
                $remote['address']['name'] ?? $remote['id'],
            ));
        }

        $template->fill([
            'lexoffice_id' => $remote['id'],
            'lexoffice_contact_id' => $remote['address']['contactId'] ?? null,
            'contact_name' => $remote['address']['name'] ?? null,
            'title' => $remote['title'] ?? null,
            'execution_interval' => $interval,
            'execution_status' => (string) ($settings['executionStatus'] ?? LexofficeRecurringTemplate::StatusActive),
            'start_date' => $settings['startDate'] ?? null,
            'end_date' => $settings['endDate'] ?? null,
            'next_execution_date' => $settings['nextExecutionDate'] ?? null,
            'last_execution_date' => $settings['lastExecutionDate'] ?? null,
            'last_execution_failed' => (bool) ($settings['lastExecutionFailed'] ?? false),
            'tax_type' => $remote['taxConditions']['taxType'] ?? $template->tax_type,
            'total_net_cents' => $this->cents($remote['totalPrice']['totalNetAmount'] ?? 0),
            'total_gross_cents' => $this->cents($remote['totalPrice']['totalGrossAmount'] ?? 0),
            'lexoffice_updated_at' => $remoteUpdatedAt,
            'synced_at' => now(),
        ]);

        // Ein neuer Kontakt an der Vorlage loest die alte Kundenzuordnung.
        if ($template->isDirty('lexoffice_contact_id')) {
            $template->customer_id = null;
        }

        $template->save();

        return $template;
    }

    /**
     * Ersetzt die Positionen und uebernimmt dabei die Zuordnungen des Portals.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function storeItems(LexofficeRecurringTemplate $template, array $lineItems): void
    {
        /** @var Collection<int, LexofficeRecurringTemplateItem> $previous */
        $previous = $template->items()->get();
        $template->items()->delete();

        $position = 0;

        foreach ($lineItems as $line) {
            // Textzeilen tragen keinen Preis und rechnen nichts ab.
            if (! is_array($line) || ! is_array($line['unitPrice'] ?? null)) {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 1);
            $discount = (float) ($line['discountPercentage'] ?? 0);
            $taxRate = isset($line['unitPrice']['taxRatePercentage']) ? (float) $line['unitPrice']['taxRatePercentage'] : null;
            $unitNet = $line['unitPrice']['netAmount']
                ?? (($line['unitPrice']['grossAmount'] ?? 0) / (1 + ($taxRate ?? 0) / 100));
            $unitNetCents = $this->cents($unitNet);

            $item = new LexofficeRecurringTemplateItem([
                'lexoffice_line_item_id' => $line['id'] ?? null,
                'position' => $position++,
                'type' => (string) ($line['type'] ?? 'custom'),
                'name' => mb_substr(trim((string) ($line['name'] ?? '')), 0, 255) ?: 'Position ohne Bezeichnung',
                'description' => $line['description'] ?? null,
                'quantity' => $quantity,
                'unit_name' => isset($line['unitName']) ? mb_substr((string) $line['unitName'], 0, 60) : null,
                'unit_price_net_cents' => $unitNetCents,
                'discount_percentage' => $discount,
                'tax_rate_percentage' => $taxRate,
                'line_net_cents' => (int) round($quantity * $unitNetCents * (1 - $discount / 100)),
            ]);

            $carried = $this->takePrevious($previous, $item);

            if ($carried !== null) {
                $item->fill([
                    'customer_service_id' => $carried->customer_service_id,
                    'match_source' => $carried->match_source,
                    'is_ignored' => $carried->is_ignored,
                ]);
            }

            $template->items()->save($item);
        }
    }

    /**
     * Die bisherige Fassung einer Position: zuerst ueber die Lexoffice-ID,
     * sonst ueber die Bezeichnung.
     *
     * @param  Collection<int, LexofficeRecurringTemplateItem>  $previous
     */
    private function takePrevious(Collection $previous, LexofficeRecurringTemplateItem $item): ?LexofficeRecurringTemplateItem
    {
        $key = $previous->search(fn (LexofficeRecurringTemplateItem $old): bool => $item->lexoffice_line_item_id !== null
            && $old->lexoffice_line_item_id === $item->lexoffice_line_item_id);

        if ($key === false) {
            $key = $previous->search(fn (LexofficeRecurringTemplateItem $old): bool => MatchText::normalize($old->name) === MatchText::normalize($item->name));
        }

        if ($key === false) {
            return null;
        }

        $found = $previous->get($key);
        $previous->forget($key);

        return $found;
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone'))->startOfSecond();
    }
}
