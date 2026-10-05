<?php

namespace App\Livewire\System;

use App\Actions\Flux\CreateFluxLedgerAccount;
use App\Actions\Flux\SyncFluxLedgerAccounts;
use App\Enums\FluxLedgerAccountType;
use App\Models\FluxLedgerAccount;
use App\Models\IntegrationCredential;
use App\Support\Flux\FluxException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Sachkonten')]
class FluxLedgerAccountList extends Component
{
    public array $input = ['number' => '', 'name' => '', 'type' => 'expense', 'is_automatic' => false];

    public string $search = '';

    public string $message = '';

    public function sync(): void
    {
        abort_unless(auth()->check(), 403);
        try {
            $count = app(SyncFluxLedgerAccounts::class)->handle(auth()->id());
            $this->message = "$count Sachkonten abgeglichen.";
        } catch (FluxException $exception) {
            $this->message = $exception->getMessage();
        }
    }

    public function create(): void
    {
        abort_unless(auth()->check(), 403);
        try {
            $account = app(CreateFluxLedgerAccount::class)->handle($this->input, auth()->id());
            $this->message = "Sachkonto {$account->number} ist mit Flux verknüpft.";
            $this->input['number'] = '';
            $this->input['name'] = '';
            $this->resetValidation();
        } catch (FluxException $exception) {
            $this->message = $exception->getMessage();
        }
    }

    public function render(): View
    {
        $tenant = (int) (IntegrationCredential::valuesFor('flux')['tenant_id'] ?? 0);

        return view('livewire.system.flux-ledger-account-list', [
            'configured' => $tenant > 0,
            'types' => FluxLedgerAccountType::cases(),
            'accounts' => FluxLedgerAccount::query()->where('provider', 'flux')->where('tenant_id', $tenant)
                ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q->where('number', 'like', '%'.$this->search.'%')->orWhere('name', 'like', '%'.$this->search.'%')))
                ->orderBy('number')->get(),
        ]);
    }
}
