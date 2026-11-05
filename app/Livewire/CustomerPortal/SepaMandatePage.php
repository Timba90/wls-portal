<?php

namespace App\Livewire\CustomerPortal;

use App\Actions\CustomerPortal\LoadSepaMandates;
use App\Actions\CustomerPortal\SubmitSepaMandate;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * SEPA-Lastschriftmandat ansehen und erteilen.
 *
 * Bestehende Mandate kommen aus Flux. Ein neues Mandat wird direkt dort
 * angelegt; das Portal haelt keine Bankdaten vor.
 */
#[Layout('components.layouts.customer')]
class SepaMandatePage extends Component
{
    use WithPortalCustomer;

    public string $accountHolder = '';

    public string $iban = '';

    public string $bic = '';

    public string $bankName = '';

    public string $type = SubmitSepaMandate::TYPE_BASIC;

    public bool $consent = false;

    public bool $showForm = false;

    public ?string $createdReference = null;

    public function mount(): void
    {
        $this->accountHolder = $this->customer()->displayName();
    }

    public function submit(SubmitSepaMandate $submitSepaMandate): void
    {
        $this->resetErrorBag();

        $mandat = $submitSepaMandate($this->portalUser(), [
            'account_holder' => $this->accountHolder,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'bank_name' => $this->bankName,
            'type' => $this->type,
            'consent' => $this->consent,
        ]);

        $this->createdReference = $mandat['mandate_reference_number'];
        $this->reset('iban', 'bic', 'bankName', 'consent', 'showForm');

        $this->dispatch('sepa-mandat-erteilt');
    }

    public function render(LoadSepaMandates $loadMandates, FluxClient $flux): View
    {
        $customer = $this->customer();
        $fehler = null;
        $mandate = [];

        try {
            $mandate = $loadMandates($customer);
        } catch (FluxException) {
            $fehler = 'Ihre Mandate können gerade nicht abgerufen werden. Bitte versuchen Sie es später erneut.';
        }

        $glaeubigerId = config('portal.customer_portal.sepa.creditor_id');

        return view('livewire.customer-portal.sepa-mandate-page', [
            'mandates' => $mandate,
            'error' => $fehler,
            'canSubmit' => $customer->isLinkedToFlux() && $flux->isConfigured() && filled($glaeubigerId),
            'linked' => $customer->isLinkedToFlux(),
            'isCompany' => $customer->isCompany(),
            'creditorName' => config('portal.customer_portal.sepa.creditor_name'),
            'creditorId' => $glaeubigerId,
        ])->title('SEPA-Mandat');
    }
}
