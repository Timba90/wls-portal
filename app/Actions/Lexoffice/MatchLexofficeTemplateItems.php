<?php

namespace App\Actions\Lexoffice;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;
use App\Models\LexofficeRecurringTemplate;
use App\Models\LexofficeRecurringTemplateItem;
use App\Support\Lexoffice\MatchText;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Ordnet Positionen der Serienrechnungen den Kundenleistungen zu.
 *
 * Automatisch nur, wenn genau eine Leistung des Kunden passt: zuerst ueber
 * die Rechnungsbezeichnung bzw. den Namen, dann ueber gleichen Betrag im
 * gleichen Rhythmus. Von Hand gesetzte Zuordnungen bleiben unangetastet.
 */
class MatchLexofficeTemplateItems
{
    /**
     * Ordnet zu, was eindeutig ist, und gibt die Zahl neuer Zuordnungen zurueck.
     */
    public function handle(): int
    {
        $this->releaseForeignAssignments();

        $templates = LexofficeRecurringTemplate::query()
            ->whereNotNull('customer_id')
            ->with('items')
            ->get();

        $services = CustomerService::query()
            ->whereIn('customer_id', $templates->pluck('customer_id')->unique())
            ->where('status', '!=', CustomerServiceStatus::Archived)
            ->get()
            ->groupBy('customer_id');

        $matched = 0;

        foreach ($templates->groupBy('customer_id') as $customerId => $customerTemplates) {
            $matched += $this->matchCustomer($customerTemplates, $services->get($customerId, collect()));
        }

        return $matched;
    }

    /**
     * Ordnet eine Position von Hand zu oder loest die Zuordnung.
     */
    public function assign(LexofficeRecurringTemplateItem $item, ?CustomerService $service): void
    {
        $item->loadMissing('template');

        if ($service !== null && $service->customer_id !== $item->template->customer_id) {
            throw ValidationException::withMessages([
                'itemAssignments.'.$item->id => 'Die Leistung gehört nicht zum verknüpften Kunden.',
            ]);
        }

        $item->update([
            'customer_service_id' => $service?->id,
            // Auch das Loesen ist eine Entscheidung: die Automatik soll die
            // Position danach nicht wieder zuordnen.
            'match_source' => LexofficeRecurringTemplateItem::MatchedManually,
            'is_ignored' => false,
        ]);
    }

    public function ignore(LexofficeRecurringTemplateItem $item, bool $ignored = true): void
    {
        $item->update([
            'is_ignored' => $ignored,
            'customer_service_id' => $ignored ? null : $item->customer_service_id,
            'match_source' => $ignored ? LexofficeRecurringTemplateItem::MatchedManually : $item->match_source,
        ]);
    }

    /**
     * @param  Collection<int, LexofficeRecurringTemplate>  $templates
     * @param  Collection<int, CustomerService>  $services
     */
    private function matchCustomer(Collection $templates, Collection $services): int
    {
        if ($services->isEmpty()) {
            return 0;
        }

        $items = $templates->flatMap(fn (LexofficeRecurringTemplate $template) => $template->items->each->setRelation('template', $template));
        $open = $items->filter(fn (LexofficeRecurringTemplateItem $item): bool => $item->customer_service_id === null
            && $item->match_source === null
            && ! $item->is_ignored);
        $matched = 0;

        foreach ($open as $item) {
            $name = MatchText::normalize($item->name);
            $candidates = $services->filter(fn (CustomerService $service): bool => $name !== ''
                && in_array($name, [MatchText::normalize($service->billing_label), MatchText::normalize($service->name)], true));

            if ($candidates->count() === 1) {
                $this->assignAutomatically($item, $candidates->first());
                $matched++;
            }
        }

        $assignedServiceIds = $items->pluck('customer_service_id')->filter()->all();
        $stillOpen = $open->filter(fn (LexofficeRecurringTemplateItem $item): bool => $item->customer_service_id === null);

        foreach ($stillOpen as $item) {
            $interval = $item->template->execution_interval->billingInterval();
            $sameAmount = fn (CustomerService $service): bool => abs($service->sales_price_cents - $item->line_net_cents) <= 1
                && abs($service->billingInterval()->monthlyFactor() - $interval->monthlyFactor()) < 1e-9;

            $candidates = $services
                ->reject(fn (CustomerService $service): bool => in_array($service->id, $assignedServiceIds, true))
                ->filter(fn (CustomerService $service): bool => $service->countsTowardsRevenue())
                ->filter($sameAmount);

            $competingItems = $stillOpen->filter(fn (LexofficeRecurringTemplateItem $other): bool => $other->customer_service_id === null
                && $other->line_net_cents === $item->line_net_cents
                && $other->template->execution_interval === $item->template->execution_interval);

            if ($candidates->count() === 1 && $competingItems->count() === 1) {
                $this->assignAutomatically($item, $candidates->first());
                $assignedServiceIds[] = $candidates->first()->id;
                $matched++;
            }
        }

        return $matched;
    }

    private function assignAutomatically(LexofficeRecurringTemplateItem $item, CustomerService $service): void
    {
        $item->update([
            'customer_service_id' => $service->id,
            'match_source' => LexofficeRecurringTemplateItem::MatchedAutomatically,
        ]);
    }

    /**
     * Zuordnungen zu Leistungen eines anderen Kunden entfallen — etwa nachdem
     * ein Kontakt in Lexoffice gewechselt wurde.
     */
    private function releaseForeignAssignments(): void
    {
        LexofficeRecurringTemplateItem::query()
            ->whereNotNull('customer_service_id')
            ->with(['template', 'customerService'])
            ->get()
            ->filter(fn (LexofficeRecurringTemplateItem $item): bool => $item->customerService?->customer_id !== $item->template->customer_id)
            ->each(fn (LexofficeRecurringTemplateItem $item) => $item->update([
                'customer_service_id' => null,
                'match_source' => null,
            ]));
    }
}
