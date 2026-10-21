<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'server' => $this->server->value, 'name' => $this->name, 'plesk_id' => $this->plesk_id, 'status' => $this->status->value, 'hosting_login' => $this->hosting_login, 'service_plan' => $this->service_plan, 'subscriber' => $this->subscriber, 'setup_date' => $this->setup_date?->toDateString(), 'ip_address' => $this->ip_address, 'expires_on' => $this->expires_on?->toDateString(), 'description' => $this->description, 'customer_id' => $this->customer_id, 'customer_service_id' => $this->customer_service_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->displayName(), 'customer_number' => $this->customer->customer_number] : null),
            'customer_service' => $this->whenLoaded('customerService', fn () => $this->customerService ? ['id' => $this->customerService->id, 'name' => $this->customerService->name, 'status' => $this->customerService->status->value, 'do_not_bill' => $this->customerService->do_not_bill, 'billing_interval_unit' => $this->customerService->billing_interval_unit->value, 'billing_interval_count' => $this->customerService->billing_interval_count] : null)];
    }
}
