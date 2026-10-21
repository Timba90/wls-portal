<?php

namespace App\Http\Controllers\Api;

use App\Actions\Subscriptions\DeleteSubscriptions;
use App\Actions\Subscriptions\SaveSubscription;
use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function destroy(Subscription $subscription, DeleteSubscriptions $delete): Response
    {
        $delete([$subscription->id]);

        return response()->noContent();
    }

    public function bulkDelete(Request $request, DeleteSubscriptions $delete): JsonResponse
    {
        $input = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100']]);

        return response()->json(['deleted' => $delete($input['ids'])]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:253'], 'server' => ['nullable', Rule::enum(PleskServer::class)], 'status' => ['nullable', Rule::enum(SubscriptionStatus::class)], 'customer_id' => ['nullable', 'integer', 'exists:customers,id'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = Subscription::query()->with(['customer', 'customerService']);
        if (filled($filters['search'] ?? null)) {
            $query->searchName($filters['search']);
        }
        foreach (['server', 'status', 'customer_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        return SubscriptionResource::collection($query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 25)->withQueryString());
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($subscription->load(['customer', 'customerService']));
    }

    public function store(Request $request, SaveSubscription $save): SubscriptionResource
    {
        return new SubscriptionResource($save($request->all())->load(['customer', 'customerService']));
    }

    public function update(Request $request, Subscription $subscription, SaveSubscription $save): SubscriptionResource
    {
        return new SubscriptionResource($save($request->all(), $subscription)->load(['customer', 'customerService']));
    }
}
