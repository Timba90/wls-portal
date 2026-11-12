<?php

namespace App\Livewire\Billing;

use App\Actions\Lexoffice\LinkLexofficeContacts;
use App\Actions\Lexoffice\MatchLexofficeTemplateItems;
use App\Actions\Lexoffice\ReconcileRecurringInvoices;
use App\Actions\Lexoffice\SyncLexofficeRecurringTemplates;
use App\Enums\CustomerServiceStatus;
use App\Enums\CustomerStatus;
use App\Enums\RecurringInvoiceMatchStatus;
use App\Jobs\SyncLexofficeRecurringTemplatesJob;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\LexofficeRecurringTemplateItem;
use App\Support\Lexoffice\LexofficeClient;
use App\Support\Lexoffice\MatchText;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Abgleich der Kundenleistungen mit den Lexoffice-Serienrechnungen (§57, §58).
 *
 * Lexoffice wird nur gelesen. Was hier entschieden wird — welcher Kontakt zu
 * welchem Kunden gehoert, welche Position welche Leistung abrechnet —, liegt
 * im Portal und uebersteht jeden Abgleich.
 */
#[Layout('components.layouts.app')]
#[Title('Serienrechnungen')]
class RecurringInvoiceReconciliation extends Component
{
    /**
     * @var array<string, string>
     */
    public const array Views = [
        'abweichungen' => 'Abweichungen',
        'fehlend' => 'Ohne Serienrechnung',
        'positionen' => 'Positionen ohne Leistung',
        'kunden' => 'Kontakte ohne Kunde',
        'stimmig' => 'Stimmig',
        'ignoriert' => 'Ignoriert',
    ];

    #[Url(as: 'ansicht')]
    public string $view = 'abweichungen';

    #[Url(as: 'suche')]
    public string $search = '';

    /**
     * Ausgewaehlter Kunde je Lexoffice-Kontakt.
     *
     * @var array<string, string|int|null>
     */
    public array $contactLinks = [];

    /**
     * Ausgewaehlte Leistung je Position.
     *
     * @var array<int|string, string|int|null>
     */
    public array $itemAssignments = [];

    public string $message = '';

    public function sync(): void
    {
        abort_unless(auth()->check(), 403);

        if (! app(LexofficeClient::class)->isConfigured()) {
            $this->message = 'Bitte zuerst unter „Schnittstellen“ einen Lexoffice-API-Schlüssel hinterlegen.';

            return;
        }

        SyncLexofficeRecurringTemplatesJob::dispatch();
        $this->message = 'Der Abgleich läuft im Hintergrund. Die Seite zeigt das Ergebnis nach dem Neuladen.';
    }

    public function linkContact(string $contactId): void
    {
        abort_unless(auth()->check(), 403);
        $customerId = $this->contactLinks[$contactId] ?? null;
        $this->validate(["contactLinks.{$contactId}" => ['required', 'integer', 'exists:customers,id']], [], [
            "contactLinks.{$contactId}" => 'Kunde',
        ]);

        app(LinkLexofficeContacts::class)->link($contactId, Customer::query()->findOrFail($customerId));
        app(MatchLexofficeTemplateItems::class)->handle();
        unset($this->contactLinks[$contactId]);
        $this->message = 'Kontakt verknüpft; eindeutige Positionen wurden zugeordnet.';
    }

    public function assignItem(int $itemId): void
    {
        abort_unless(auth()->check(), 403);
        $this->validate(["itemAssignments.{$itemId}" => ['required', 'integer', 'exists:customer_services,id']], [], [
            "itemAssignments.{$itemId}" => 'Leistung',
        ]);

        app(MatchLexofficeTemplateItems::class)->assign(
            LexofficeRecurringTemplateItem::query()->findOrFail($itemId),
            CustomerService::query()->findOrFail($this->itemAssignments[$itemId]),
        );
        unset($this->itemAssignments[$itemId]);
        $this->message = 'Position zugeordnet.';
    }

    public function releaseItem(int $itemId): void
    {
        abort_unless(auth()->check(), 403);
        app(MatchLexofficeTemplateItems::class)->assign(LexofficeRecurringTemplateItem::query()->findOrFail($itemId), null);
        $this->message = 'Zuordnung gelöst.';
    }

    public function ignoreItem(int $itemId): void
    {
        abort_unless(auth()->check(), 403);
        app(MatchLexofficeTemplateItems::class)->ignore(LexofficeRecurringTemplateItem::query()->findOrFail($itemId));
        $this->message = 'Position wird ignoriert.';
    }

    public function restoreItem(int $itemId): void
    {
        abort_unless(auth()->check(), 403);
        app(MatchLexofficeTemplateItems::class)->ignore(LexofficeRecurringTemplateItem::query()->findOrFail($itemId), false);
        $this->message = 'Position wird wieder abgeglichen.';
    }

    public function updatedView(): void
    {
        if (! array_key_exists($this->view, self::Views)) {
            $this->view = 'abweichungen';
        }
    }

    public function render(): View
    {
        $this->updatedView();
        $report = app(ReconcileRecurringInvoices::class)->handle();

        $deviations = $report['services']->filter(fn (array $row): bool => in_array($row['status'], [
            RecurringInvoiceMatchStatus::AmountDeviation,
            RecurringInvoiceMatchStatus::IntervalDeviation,
            RecurringInvoiceMatchStatus::InvoicedButNotBillable,
        ], true));
        $missing = $report['services']->where('status', RecurringInvoiceMatchStatus::NotInvoiced);
        $matching = $report['services']->where('status', RecurringInvoiceMatchStatus::Matching);

        $counts = [
            'abweichungen' => $deviations->count(),
            'fehlend' => $missing->count(),
            'positionen' => $report['open_items']->count(),
            'kunden' => $report['unlinked_contacts']->count(),
            'stimmig' => $matching->count(),
            'ignoriert' => $report['ignored_items']->count(),
        ];

        $rows = match ($this->view) {
            'abweichungen' => $deviations,
            'fehlend' => $missing,
            'stimmig' => $matching,
            'positionen' => $report['open_items'],
            'kunden' => $report['unlinked_contacts'],
            'ignoriert' => $report['ignored_items'],
        };

        return view('livewire.billing.recurring-invoice-reconciliation', [
            'configured' => app(LexofficeClient::class)->isConfigured(),
            'lastRun' => Cache::get(SyncLexofficeRecurringTemplates::LastRunCacheKey),
            'totals' => $report['totals'],
            'counts' => $counts,
            'rows' => $this->applySearch($rows)->values(),
            'customerOptions' => $this->view === 'kunden' ? $this->customerOptions() : [],
            'serviceOptions' => $this->view === 'positionen' ? $this->serviceOptions($report['open_items']) : [],
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, mixed>
     */
    private function applySearch(Collection $rows): Collection
    {
        $needle = MatchText::normalize($this->search);

        if ($needle === '') {
            return $rows;
        }

        return $rows->filter(function (mixed $row) use ($needle): bool {
            $haystack = match (true) {
                $row instanceof LexofficeRecurringTemplateItem => [$row->name, $row->template->contact_name, $row->template->customer?->displayName()],
                isset($row['service']) => [$row['service']->name, $row['service']->billing_label, $row['service']->customer->displayName()],
                default => [$row['name'] ?? ''],
            };

            return str_contains(MatchText::normalize(implode(' ', array_filter($haystack))), $needle);
        });
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function customerOptions(): array
    {
        return Customer::query()
            ->where('status', '!=', CustomerStatus::Archived)
            ->orderBy('company_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->displayName().' · '.$customer->customer_number
                    .($customer->lexoffice_contact_id ? ' (bereits verknüpft)' : ''),
            ])
            ->all();
    }

    /**
     * Auswahl der Leistungen je Kunde der offenen Positionen.
     *
     * @param  Collection<int, LexofficeRecurringTemplateItem>  $items
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    private function serviceOptions(Collection $items): array
    {
        return CustomerService::query()
            ->whereIn('customer_id', $items->map(fn (LexofficeRecurringTemplateItem $item) => $item->template->customer_id)->unique())
            ->where('status', '!=', CustomerServiceStatus::Archived)
            ->orderBy('name')
            ->get()
            ->groupBy('customer_id')
            ->map(fn (Collection $services) => $services->map(fn (CustomerService $service): array => [
                'id' => $service->id,
                'name' => sprintf('%s · %s %s · %s', $service->billing_label ?: $service->name, $service->salesPrice()->format(), $service->billingInterval()->label(), $service->status->label()),
            ])->values()->all())
            ->all();
    }
}
