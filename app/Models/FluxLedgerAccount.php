<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FluxLedgerAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'flux_id' => 'integer', 'is_automatic' => 'boolean', 'synced_at' => 'datetime'];
    }

    public static function remember(array $remote, int $userId): self
    {
        $account = static::query()->firstOrNew([
            'provider' => 'flux', 'tenant_id' => $remote['tenant_id'], 'number' => (string) $remote['number'],
        ]);
        if (! $account->exists) {
            $account->created_by = $userId;
        }
        $account->fill([
            'name' => $remote['name'], 'type' => $remote['ledger_account_type_enum'],
            'is_automatic' => $remote['is_automatic'], 'flux_id' => $remote['id'],
            'synced_at' => now(), 'updated_by' => $userId,
        ])->save();

        return $account;
    }
}
