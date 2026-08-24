<?php

namespace App\Livewire\Services;

use App\Actions\Pricing\CancelPriceChange;
use App\Actions\Pricing\SchedulePriceChange;
use App\Actions\Services\ArchiveCustomerService;
use App\Actions\Services\ChangeCustomerServiceStatus;
use App\Actions\Services\RestoreCustomerService;
use App\Actions\Services\SetDoNotBill;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Enums\PriceType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Detailseite einer Kundenleistung.
 */
#[Layout('components.layouts.app')]
class CustomerServiceDetail extends Component
{
    public Customer $customer;

    public CustomerService $service;

    #[Url(as: 'bereich', except: 'preise')]
    public string $tab = 'preise';

    public bool $showDoNotBillForm = false;

    public string $doNotBillReason = DoNotBillReason::Included->value;

    public bool $showPriceChangeForm = false;

    public string $priceChangeType = PriceType::Sales->value;

    public string $priceChangeValue = '';

    public string $priceChangeEffectiveDate = '';

    public string $priceChangeNote = '';

    public function mount(Customer $customer, CustomerService $service): void
    {
        abort_unless($service->customer_id === $customer->getKey(), 404);

        $this->customer = $customer;
        $this->service = $service;
    }

    public function openPriceChangeForm(string $type): void
    {
        $this->resetValidation();

        $this->priceChangeType = $type;
        $this->priceChangeValue = Money::fromCents(
            $this->service->{PriceType::from($type)->column()},
        )->toInput();
        $this->priceChangeEffectiveDate = now()->toDateString();
        $this->priceChangeNote = '';
        $this->showPriceChangeForm = true;
    }

    public function savePriceChange(SchedulePriceChange $schedulePriceChange): void
    {
        $this->validate([
            'priceChangeType' => ['required', Rule::in(PriceType::values())],
            'priceChangeValue' => ['required', 'string'],
            // Rueckwirkende Preisaenderungen sind ausgeschlossen.
            'priceChangeEffectiveDate' => ['required', 'date', 'after_or_equal:today'],
            'priceChangeNote' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'priceChangeType' => 'Preisart',
            'priceChangeValue' => 'Neuer Preis',
            'priceChangeEffectiveDate' => 'Wirksamkeitsdatum',
            'priceChangeNote' => 'Notiz',
        ]);

        try {
            $neuerPreis = Money::fromEuroInput($this->priceChangeValue);
        } catch (\InvalidArgumentException) {
            $this->addError('priceChangeValue', 'Der Wert ist kein gültiger Geldbetrag.');

            return;
        }

        $schedulePriceChange(
            service: $this->service,
            type: PriceType::from($this->priceChangeType),
            newPrice: $neuerPreis,
            effectiveDate: Carbon::parse($this->priceChangeEffectiveDate),
            user: auth()->user(),
            note: $this->priceChangeNote ?: null,
        );

        $this->service->refresh();
        $this->showPriceChangeForm = false;

        $this->dispatch('preisaenderung-gespeichert');
    }

    public function cancelPriceChange(int $priceChangeId, CancelPriceChange $cancelPriceChange): void
    {
        $change = $this->service->priceChanges()->findOrFail($priceChangeId);

        $cancelPriceChange($change);

        $this->dispatch('preisaenderung-geloescht');
    }

    public function changeStatus(string $status, ChangeCustomerServiceStatus $changeStatus): void
    {
        $changeStatus($this->service, CustomerServiceStatus::from($status));

        $this->service->refresh();

        $this->dispatch('status-geaendert');
    }

    public function markDoNotBill(SetDoNotBill $setDoNotBill): void
    {
        $this->validate(
            ['doNotBillReason' => ['required', Rule::in(DoNotBillReason::values())]],
            attributes: ['doNotBillReason' => 'Grund'],
        );

        $setDoNotBill->mark($this->service, DoNotBillReason::from($this->doNotBillReason));

        $this->service->refresh();
        $this->showDoNotBillForm = false;

        $this->dispatch('nicht-abrechnen-gesetzt');
    }

    public function releaseDoNotBill(SetDoNotBill $setDoNotBill): void
    {
        $setDoNotBill->release($this->service);

        $this->service->refresh();

        $this->dispatch('nicht-abrechnen-entfernt');
    }

    public function archive(ArchiveCustomerService $archiveCustomerService): void
    {
        $archiveCustomerService($this->service);

        $this->service->refresh();

        $this->dispatch('leistung-archiviert');
    }

    public function restore(RestoreCustomerService $restoreCustomerService): void
    {
        $restoreCustomerService($this->service);

        $this->service->refresh();

        $this->dispatch('leistung-reaktiviert');
    }

    /**
     * Vertragsdaten fuer die rechte Spalte, in der Reihenfolge des Entwurfs.
     *
     * @return array<string, ?string>
     */
    public function contractData(): array
    {
        $intervall = $this->service->billingInterval();

        return [
            'Status' => $this->service->status->label(),
            'Turnus' => $intervall->label(),
            'Leistungsbeginn' => $this->service->service_start_date?->format('d.m.Y'),
            'Abrechnungsbeginn' => $this->service->billing_start_date?->format('d.m.Y'),
            'Erste Abrechnung' => $this->service->first_billing_date?->format('d.m.Y'),
            'Einkaufspreis' => $this->service->purchasePrice()->format(),
            'Verkaufspreis' => $this->service->salesPrice()->format(),
            'Marge' => $this->service->margin()->format().($this->service->marginPercentage() !== null
                ? ' ('.number_format($this->service->marginPercentage(), 1, ',', '.').' %)'
                : ''),
            'Umsatz / Jahr' => $this->service->yearlyRevenue()->format(),
            'Verantwortlich' => $this->service->responsibleUser?->name,
        ];
    }

    /**
     * Abweichung des Verkaufspreises vom Listenpreis des Basisartikels, in Cent.
     *
     * Positiv bedeutet teurer als der Katalog, negativ guenstiger. Ohne
     * Basisartikel gibt es nichts zu vergleichen.
     */
    public function priceDeviation(): int
    {
        if (! $this->service->product) {
            return 0;
        }

        return $this->service->sales_price_cents - $this->service->product->default_sales_price_cents;
    }

    public function render(): View
    {
        $this->service->load([
            'product', 'productVariant', 'category', 'subcategory',
            'responsibleUser', 'tags', 'serviceComponents',
        ]);

        return view('livewire.services.customer-service-detail', [
            'statusOptions' => CustomerServiceStatus::options(CustomerServiceStatus::selectable()),
            'doNotBillReasonOptions' => DoNotBillReason::options(),
            'priceTypeOptions' => PriceType::options(),
            'deviations' => $this->service->catalogDeviations(),
            'scheduledPriceChanges' => $this->scheduledPriceChanges(),
            'appliedPriceChanges' => $this->appliedPriceChanges(),
        ])->title($this->service->name);
    }

    /**
     * @return Collection<int, PriceChange>
     */
    private function scheduledPriceChanges(): Collection
    {
        return $this->service
            ->priceChanges()
            ->scheduled()
            ->with('user')
            ->orderBy('effective_date')
            ->get();
    }

    /**
     * @return Collection<int, PriceChange>
     */
    private function appliedPriceChanges(): Collection
    {
        return $this->service
            ->priceChanges()
            ->whereNotNull('applied_at')
            ->with('user')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();
    }
}
