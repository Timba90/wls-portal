<?php

namespace App\Actions\Flux;

use App\Enums\FluxLedgerAccountType;
use App\Models\FluxLedgerAccount;
use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateFluxLedgerAccount
{
    public function handle(array $input, int $userId): FluxLedgerAccount
    {
        $input = Validator::make($input, [
            'number' => ['required', 'string', 'regex:/^\d{4}$/'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(FluxLedgerAccountType::class)],
            'is_automatic' => ['required', 'boolean'],
        ])->validate();
        $client = app(FluxClient::class);
        try {
            return Cache::lock('flux-ledger:'.$client->tenantId().':'.$input['number'], 120)
                ->block(5, function () use ($client, $input, $userId) {
                    // 60s preflight plus at most 20s POST stays below the 120s lease.
                    $deadline = hrtime(true) / 1e9 + 60;
                    $remote = $this->find($client, $input, $deadline);
                    if ($remote === null) {
                        if (hrtime(true) / 1e9 >= $deadline) {
                            throw new FluxException('Der Kontenabgleich dauert zu lange. Bitte später erneut abgleichen.');
                        }
                        try {
                            $remote = $client->createLedgerAccount([
                                'number' => $input['number'], 'name' => $input['name'],
                                'ledger_account_type_enum' => $input['type'], 'is_automatic' => (bool) $input['is_automatic'],
                            ]);
                        } catch (FluxException) {
                            try {
                                $remote = $this->find($client, $input, $deadline);
                            } catch (FluxException) {
                                $remote = null;
                            }
                            if ($remote === null) {
                                throw new FluxException('Die Anlage konnte nicht bestätigt werden. Bitte zuerst den Kontenbestand abgleichen; es wurde kein zweiter Anlageversuch ausgeführt.');
                            }
                        }
                    }

                    return FluxLedgerAccount::remember($remote, $userId);
                });
        } catch (LockTimeoutException) {
            throw new FluxException('Diese Kontonummer wird bereits bearbeitet. Bitte später den Bestand abgleichen.');
        }
    }

    private function find(FluxClient $client, array $input, float $deadline): ?array
    {
        $matches = collect($client->ledgerAccounts($deadline))->filter(fn ($a) => (int) $a['tenant_id'] === $client->tenantId() && (string) $a['number'] === $input['number']);
        if ($matches->count() > 1) {
            throw new FluxException('Mehrere Flux-Konten haben diese Nummer. Bitte den Bestand prüfen.');
        }
        $account = $matches->first();
        if ($account && $account['ledger_account_type_enum'] !== $input['type']) {
            throw new FluxException('Diese Kontonummer ist in Flux mit einem anderen Kontentyp belegt.');
        }

        return $account;
    }
}
