<?php

namespace App\Livewire\Subscriptions;

use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SubscriptionDetail extends Component
{
    public Subscription $subscription;

    public function mount(Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function render(): View
    {
        $this->subscription->load(['customer', 'customerService']);

        return view('livewire.subscriptions.subscription-detail')->title($this->subscription->name);
    }
}
