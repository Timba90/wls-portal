<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeleteSubscriptions
{
    /** @param array<int, mixed> $ids */
    public function __invoke(array $ids): int
    {
        $validated = Validator::make(['ids' => $ids], [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ])->validate();

        return DB::transaction(function () use ($validated): int {
            $subscriptions = Subscription::query()->whereIn('id', $validated['ids'])->orderBy('id')->lockForUpdate()->get();
            if ($subscriptions->count() !== count($validated['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Mindestens eine Subscription existiert nicht mehr. Bitte die Auswahl aktualisieren.']);
            }
            foreach ($subscriptions as $subscription) {
                $subscription->delete();
            }

            return $subscriptions->count();
        });
    }
}
