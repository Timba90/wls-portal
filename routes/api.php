<?php

use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Middleware\EnsureMcpScope;
use Illuminate\Support\Facades\Route;

Route::name('api.')->middleware(['auth:sanctum,api', EnsureMcpScope::class, 'throttle:'.config('portal.mcp.rate_limit')])->group(function (): void {
    Route::post('subscriptions/bulk-delete', [SubscriptionController::class, 'bulkDelete'])->name('subscriptions.bulk-delete');
    Route::apiResource('subscriptions', SubscriptionController::class);
});
