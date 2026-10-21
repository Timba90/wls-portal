<?php

namespace App\Models;

use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['server', 'name', 'plesk_id', 'status', 'hosting_login', 'service_plan', 'subscriber', 'setup_date', 'ip_address', 'expires_on', 'description', 'customer_id', 'customer_service_id'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use Auditable, HasFactory;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    public function scopeSearchName(Builder $query, string $search): void
    {
        $query->whereRaw('subscriptions.name COLLATE utf8mb4_unicode_ci LIKE ?', ['%'.$search.'%']);
    }

    protected function casts(): array
    {
        return ['server' => PleskServer::class, 'status' => SubscriptionStatus::class, 'plesk_id' => 'integer', 'expires_on' => 'date', 'setup_date' => 'date'];
    }

    public function auditLabels(): array
    {
        return ['server' => 'Server', 'name' => 'Hauptdomain', 'plesk_id' => 'Plesk-ID', 'status' => 'Status', 'hosting_login' => 'Hosting-Login', 'service_plan' => 'Serviceplan', 'subscriber' => 'Subscriber', 'setup_date' => 'Angelegt am', 'ip_address' => 'IP-Adresse', 'expires_on' => 'Läuft ab am', 'description' => 'Beschreibung', 'customer_id' => 'Kunde', 'customer_service_id' => 'Kundenleistung'];
    }
}
