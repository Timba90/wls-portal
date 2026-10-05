<?php

namespace App\Actions\Flux;

use App\Models\FluxLedgerAccount;
use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Support\Facades\DB;

class SyncFluxLedgerAccounts
{
    public function handle(int $userId): int
    {
        $client = app(FluxClient::class);
        $accounts = collect($client->ledgerAccounts())->where('tenant_id', $client->tenantId());
        if ($accounts->pluck('number')->map(fn ($n) => (string) $n)->duplicates()->isNotEmpty()) {
            throw new FluxException('Mehrere Flux-Konten haben dieselbe Nummer. Bitte den Bestand prüfen.');
        }
        DB::transaction(function () use ($accounts, $userId) {
            foreach ($accounts as $account) {
                FluxLedgerAccount::remember($account, $userId);
            }
        });

        return $accounts->count();
    }
}
