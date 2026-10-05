<?php

namespace App\Actions\Flux;

use App\Models\IntegrationCredential;
use App\Support\Flux\FluxClient;
use Illuminate\Support\Arr;

class SaveFluxCredentials
{
    public function handle(array $input, int $userId): void
    {
        $values = array_merge(IntegrationCredential::valuesFor('flux'), array_map('trim', array_filter(
            Arr::only($input, ['base_url', 'token', 'tenant_id']),
            fn ($value) => is_string($value) && trim($value) !== '',
        )));
        FluxClient::validateCredentials($values);
        $values['base_url'] = rtrim($values['base_url'], '/');
        IntegrationCredential::query()->updateOrCreate(['provider' => 'flux'], [
            'credentials' => $values, 'updated_by' => $userId,
        ]);
    }
}
