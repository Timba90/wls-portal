<?php

namespace App\Actions\Subscriptions;

use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use App\Models\Domain;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveSubscription
{
    /** @param array<string,mixed> $attributes */
    public function __invoke(array $attributes, ?Subscription $subscription = null): Subscription
    {
        $subscription ??= new Subscription;
        $existing = $subscription->getAttributes();
        foreach (['expires_on', 'setup_date'] as $date) {
            if (isset($existing[$date])) {
                $existing[$date] = $subscription->getAttribute($date)?->toDateString();
            }
        }
        $values = array_replace(['status' => 'active'], $existing, $attributes);
        if (isset($values['name']) && is_string($values['name'])) {
            $values['name'] = Domain::normalizeName($values['name']);
        }
        foreach (['server', 'status'] as $field) {
            if (($values[$field] ?? null) instanceof \BackedEnum) {
                $values[$field] = $values[$field]->value;
            }
        }
        if (array_key_exists('customer_id', $attributes) && $attributes['customer_id'] === null) {
            $values['customer_service_id'] = null;
        }
        $server = $values['server'] ?? null;
        $validated = Validator::make($values, [
            'server' => ['required', Rule::enum(PleskServer::class)],
            'name' => ['required', 'string', 'max:253', 'regex:/^[^\s\/]+\.[^\s\/]+$/', Rule::unique('subscriptions', 'name')->where('server', $server)->ignore($subscription->id)],
            'plesk_id' => ['nullable', 'integer', 'min:1', Rule::unique('subscriptions', 'plesk_id')->where('server', $server)->ignore($subscription->id)],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'hosting_login' => ['nullable', 'string', 'max:128'], 'service_plan' => ['nullable', 'string', 'max:255'], 'subscriber' => ['nullable', 'string', 'max:255'], 'setup_date' => ['nullable', 'date_format:Y-m-d'], 'ip_address' => ['nullable', 'ip', 'max:45'], 'expires_on' => ['nullable', 'date_format:Y-m-d'], 'description' => ['nullable', 'string', 'max:65535'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'customer_service_id' => ['nullable', 'integer', Rule::exists('customer_services', 'id')->where('customer_id', $values['customer_id'] ?? null)],
        ])->validate();

        return DB::transaction(function () use ($subscription, $validated): Subscription {
            $subscription->fill($validated)->save();

            return $subscription;
        });
    }
}
