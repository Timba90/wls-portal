<?php

namespace App\Actions\Lexoffice;

use App\Enums\CustomerServiceStatus;
use App\Enums\RecurringInvoiceMatchStatus;
use App\Models\CustomerService;
use App\Models\LexofficeRecurringTemplate;
use App\Models\LexofficeRecurringTemplateItem;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Stellt Kundenleistungen und Lexoffice-Serienrechnungen gegenueber (§58).
 *
 * Liest nur. Verglichen wird der Jahreswert, weil Leistung und Vorlage in
 * unterschiedlichen Rhythmen abrechnen koennen; Abweichungen bis zu 12 Cent
 * im Jahr sind Rundung und gelten als stimmig.
 */
class ReconcileRecurringInvoices
{
    private const int YearlyToleranceCents = 12;

    /**
     * @return array{
     *     services: Collection<int, array{service: CustomerService, items: Collection<int, LexofficeRecurringTemplateItem>, status: RecurringInvoiceMatchStatus, expected_yearly: Money, invoiced_yearly: Money}>,
     *     open_items: Collection<int, LexofficeRecurringTemplateItem>,
     *     ignored_items: Collection<int, LexofficeRecurringTemplateItem>,
     *     unlinked_contacts: Collection<int, array{contact_id: string, name: string, templates: int, yearly: Money}>,
     *     totals: array{active_templates: int, invoiced_yearly: Money, expected_yearly: Money}
     * }
     */
    public function handle(): array
    {
        $activeTemplates = LexofficeRecurringTemplate::query()
            ->active()
            ->with(['customer', 'items.customerService'])
            ->get();

        $activeItems = $activeTemplates->flatMap(
            fn (LexofficeRecurringTemplate $template) => $template->items->each->setRelation('template', $template)
        );

        $itemsByService = $activeItems
            ->filter(fn (LexofficeRecurringTemplateItem $item): bool => $item->customer_service_id !== null)
            ->groupBy('customer_service_id');

        $services = CustomerService::query()
            ->with('customer')
            ->where('status', '!=', CustomerServiceStatus::Archived)
            ->get()
            ->map(fn (CustomerService $service): ?array => $this->compare($service, $itemsByService->get($service->id, collect())))
            ->filter()
            ->sortBy(fn (array $row): string => $row['service']->customer->displayName().' '.$row['service']->name)
            ->values();

        return [
            'services' => $services,
            'open_items' => $activeItems
                ->filter(fn (LexofficeRecurringTemplateItem $item): bool => $item->template->customer_id !== null
                    && $item->customer_service_id === null
                    && ! $item->is_ignored)
                ->sortBy(fn (LexofficeRecurringTemplateItem $item): string => $item->template->customer->displayName())
                ->values(),
            'ignored_items' => $activeItems->filter(fn (LexofficeRecurringTemplateItem $item): bool => $item->is_ignored)->values(),
            'unlinked_contacts' => $activeTemplates
                ->whereNull('customer_id')
                ->groupBy(fn (LexofficeRecurringTemplate $template): string => (string) $template->lexoffice_contact_id)
                ->map(fn (Collection $templates, string $contactId): array => [
                    'contact_id' => $contactId,
                    'name' => (string) $templates->first()->contact_name,
                    'templates' => $templates->count(),
                    'yearly' => $templates->reduce(
                        fn (Money $sum, LexofficeRecurringTemplate $template): Money => $sum->plus(
                            $template->execution_interval->billingInterval()->toYearly($template->totalNet())
                        ),
                        Money::zero(),
                    ),
                ])
                ->sortBy('name')
                ->values(),
            'totals' => [
                'active_templates' => $activeTemplates->count(),
                'invoiced_yearly' => $activeItems->reduce(fn (Money $sum, LexofficeRecurringTemplateItem $item): Money => $sum->plus($item->yearlyNet()), Money::zero()),
                'expected_yearly' => $services->reduce(
                    fn (Money $sum, array $row): Money => $row['service']->countsTowardsRevenue() ? $sum->plus($row['expected_yearly']) : $sum,
                    Money::zero(),
                ),
            ],
        ];
    }

    /**
     * @param  Collection<int, LexofficeRecurringTemplateItem>  $items
     * @return array{service: CustomerService, items: Collection<int, LexofficeRecurringTemplateItem>, status: RecurringInvoiceMatchStatus, expected_yearly: Money, invoiced_yearly: Money}|null
     */
    private function compare(CustomerService $service, Collection $items): ?array
    {
        $billable = $service->countsTowardsRevenue();

        if (! $billable && $items->isEmpty()) {
            return null;
        }

        $expected = $service->billingInterval()->isRecurring() ? $service->yearlyRevenue() : $service->salesPrice();
        $invoiced = $items->reduce(fn (Money $sum, LexofficeRecurringTemplateItem $item): Money => $sum->plus($item->yearlyNet()), Money::zero());

        $status = match (true) {
            $items->isEmpty() => RecurringInvoiceMatchStatus::NotInvoiced,
            ! $billable => RecurringInvoiceMatchStatus::InvoicedButNotBillable,
            abs($expected->cents - $invoiced->cents) > self::YearlyToleranceCents => RecurringInvoiceMatchStatus::AmountDeviation,
            $items->contains(fn (LexofficeRecurringTemplateItem $item): bool => abs(
                $item->template->execution_interval->billingInterval()->monthlyFactor() - $service->billingInterval()->monthlyFactor()
            ) > 1e-9) => RecurringInvoiceMatchStatus::IntervalDeviation,
            default => RecurringInvoiceMatchStatus::Matching,
        };

        return [
            'service' => $service,
            'items' => $items,
            'status' => $status,
            'expected_yearly' => $expected,
            'invoiced_yearly' => $invoiced,
        ];
    }
}
