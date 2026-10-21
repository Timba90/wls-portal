<?php

namespace App\Livewire\Subscriptions;

use App\Actions\Subscriptions\SaveSubscription;
use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SubscriptionForm extends Component
{
    public ?Subscription $subscription = null;

    public ?string $server = null;

    public string $name = '';

    public ?string $plesk_id = null;

    public ?string $status = 'active';

    public string $subscriber = '';

    public string $setup_date = '';

    public string $hosting_login = '';

    public string $service_plan = '';

    public string $ip_address = '';

    public string $expires_on = '';

    public string $description = '';

    public ?string $customer_id = null;

    public ?string $customer_service_id = null;

    public function mount(?Subscription $subscription = null): void
    {
        if (! $subscription?->exists) {
            return;
        }

        $this->subscription = $subscription;
        $this->server = $subscription->server->value;
        $this->status = $subscription->status->value;
        foreach (['name', 'subscriber', 'hosting_login', 'service_plan', 'ip_address', 'description'] as $field) {
            $this->{$field} = $subscription->{$field} ?? '';
        }
        foreach (['plesk_id', 'customer_id', 'customer_service_id'] as $field) {
            $this->{$field} = $subscription->{$field} === null ? null : (string) $subscription->{$field};
        }
        $this->setup_date = $subscription->setup_date?->format('Y-m-d') ?? '';
        $this->expires_on = $subscription->expires_on?->format('Y-m-d') ?? '';
    }

    public function updatedCustomerId(): void
    {
        $this->customer_service_id = null;
    }

    public function isEditing(): bool
    {
        return $this->subscription !== null;
    }

    public function save(SaveSubscription $save): void
    {
        abort_unless(auth()->check(), 403);
        $attributes = [];
        foreach (['server', 'name', 'plesk_id', 'status', 'subscriber', 'setup_date', 'hosting_login', 'service_plan', 'ip_address', 'expires_on', 'description', 'customer_id', 'customer_service_id'] as $field) {
            $attributes[$field] = $this->{$field} === '' ? null : $this->{$field};
        }
        $subscription = $save(attributes: $attributes, subscription: $this->subscription);
        session()->flash('status', $this->isEditing() ? 'Subscription gespeichert.' : 'Subscription angelegt.');
        $this->redirectRoute('subscriptions.show', $subscription, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.subscriptions.subscription-form', [
            'serverOptions' => PleskServer::options(),
            'statusOptions' => SubscriptionStatus::options(),
            'customerOptions' => Customer::query()->orderBy('company_name')->orderBy('last_name')->get()
                ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->customer_number.' · '.$customer->displayName()]),
            'serviceOptions' => filled($this->customer_id)
                ? CustomerService::query()->where('customer_id', $this->customer_id)->orderBy('name')->get()
                    ->map(fn (CustomerService $service): array => ['id' => $service->id, 'name' => $service->billing_label ?: $service->name])
                : collect(),
        ]);
    }
}
