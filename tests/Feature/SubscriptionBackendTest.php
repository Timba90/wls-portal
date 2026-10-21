<?php

use App\Actions\Subscriptions\SaveSubscription;
use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Subscriptions\SubscriptionLesen;
use App\Mcp\Tools\Subscriptions\SubscriptionSpeichern;
use App\Mcp\Tools\Subscriptions\SubscriptionSuchen;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

it('normalizes manual subscriptions and preserves omitted fields', function () {
    $save = app(SaveSubscription::class);
    $subscription = $save(['server' => 'cloud01', 'name' => ' Example.DE. ', 'hosting_login' => 'test']);
    expect($subscription->name)->toBe('example.de');
    $save(['description' => 'Changed'], $subscription);
    expect($subscription->fresh()->hosting_login)->toBe('test');
});
it('enforces identity within each server', function () {
    $save = app(SaveSubscription::class);
    $save(['server' => 'cloud01', 'name' => 'example.de', 'plesk_id' => 1]);
    $save(['server' => 'cloud02', 'name' => 'example.de', 'plesk_id' => 1]);
    expect(fn () => $save(['server' => 'cloud01', 'name' => 'example.de']))->toThrow(ValidationException::class);
    expect(fn () => $save(['server' => 'cloud01', 'name' => 'other.de', 'plesk_id' => 1]))->toThrow(ValidationException::class);
});
it('validates linked service ownership and clears it with the customer', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->create(['customer_id' => $customer->id]);
    $save = app(SaveSubscription::class);
    $subscription = $save(['server' => 'cloud01', 'name' => 'example.de', 'customer_id' => $customer->id, 'customer_service_id' => $service->id]);
    expect(fn () => $save(['customer_id' => Customer::factory()->create()->id], $subscription))->toThrow(ValidationException::class);
    $save(['customer_id' => null], $subscription);
    expect($subscription->fresh()->customer_service_id)->toBeNull();
});
it('requires authentication on the subscriptions api', function () {
    $this->postJson('/api/subscriptions', ['server' => 'cloud01', 'name' => 'example.de'])->assertUnauthorized();
});
it('uses personal tokens for create read partial update and pagination', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('API')->plainTextToken);
    $id = $this->postJson('/api/subscriptions', ['server' => 'cloud03', 'name' => 'Example.DE.', 'hosting_login' => 'login'])->assertCreated()->json('data.id');
    $this->patchJson('/api/subscriptions/'.$id, ['description' => 'Updated'])->assertOk()->assertJsonPath('data.hosting_login', 'login');
    $this->getJson('/api/subscriptions?server=cloud03&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/subscriptions/'.$id)->assertOk()->assertJsonPath('data.name', 'example.de');
    $this->assertDatabaseHas('audit_logs', ['auditable_type' => Subscription::class, 'auditable_id' => $id, 'user_id' => $user->id]);
});

it('shares manual save semantics with MCP and marks reads readonly', function () {
    $user = User::factory()->create();
    PortalServer::actingAs($user)->tool(SubscriptionSpeichern::class, ['server' => 'cloud02', 'name' => 'MCP.DE.', 'hosting_login' => 'keep'])->assertOk()->assertSee('mcp.de');
    $subscription = Subscription::query()->firstOrFail();
    PortalServer::actingAs($user)->tool(SubscriptionSpeichern::class, ['id' => $subscription->id, 'description' => 'MCP'])->assertOk();
    expect($subscription->fresh()->hosting_login)->toBe('keep');
    PortalServer::actingAs($user)->tool(SubscriptionSuchen::class, ['server' => 'cloud02'])->assertOk()->assertSee('mcp.de');
    PortalServer::actingAs($user)->tool(SubscriptionLesen::class, ['id' => $subscription->id])->assertOk()->assertSee('MCP');
    $tools = $this->withToken($user->createToken('tools')->plainTextToken)->postJson(route('mcp.portal'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->json('result.tools');
    foreach (['subscription-suchen', 'subscription-lesen'] as $name) {
        expect(collect($tools)->firstWhere('name', $name)['annotations']['readOnlyHint'])->toBeTrue();
    }
});
it('preserves an expiry date and explicitly clears optional fields', function () {
    $save = app(SaveSubscription::class);
    $subscription = $save(['server' => 'cloud01', 'name' => 'expiry.de', 'expires_on' => '2027-01-01', 'plesk_id' => 5]);
    $save(['description' => 'edit'], $subscription->fresh());
    $save(['expires_on' => null, 'plesk_id' => null], $subscription->fresh());
    expect($subscription->fresh()->expires_on)->toBeNull()->and($subscription->fresh()->plesk_id)->toBeNull();
});
it('rejects invalid manual metadata', function (array $invalid, string $field) {
    $this->withToken(User::factory()->create()->createToken('API')->plainTextToken)->postJson('/api/subscriptions', array_replace(['server' => 'cloud01', 'name' => 'invalid.de'], $invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['server' => 'cloud04'], 'server'], [['plesk_id' => 0], 'plesk_id'], [['plesk_id' => -1], 'plesk_id'], [['ip_address' => 'bad'], 'ip_address'], [['expires_on' => '2027-02-30'], 'expires_on'], [['name' => ''], 'name'],
]);
it('rejects an explicit foreign customer service', function () {
    $customer = Customer::factory()->create();
    $foreign = CustomerService::factory()->create();
    $this->withToken(User::factory()->create()->createToken('API')->plainTextToken)->postJson('/api/subscriptions', ['server' => 'cloud01', 'name' => 'foreign.de', 'customer_id' => $customer->id, 'customer_service_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('customer_service_id');
});
it('rejects revoked personal tokens', function () {
    $token = User::factory()->create()->createToken('revoked');
    $token->accessToken->delete();
    $this->withToken($token->plainTextToken)->getJson('/api/subscriptions')->assertUnauthorized();
});
it('rejects invalid personal tokens', function () {
    $this->withToken('invalid-token')->postJson('/api/subscriptions', ['server' => 'cloud01', 'name' => 'invalid.de'])->assertUnauthorized();
});
it('requires the OAuth scope for API access', function () {
    Passport::actingAs(User::factory()->create(), []);
    $this->getJson('/api/subscriptions')->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
});
it('accepts scoped OAuth for API access', function () {
    Passport::actingAs(User::factory()->create(), [Registrar::OAUTH_SCOPE]);
    $this->postJson('/api/subscriptions', ['server' => 'cloud01', 'name' => 'oauth.de'])->assertCreated();
});
it('does not permit deletion without a valid token', function () {
    $subscription = Subscription::factory()->create();
    $this->withToken('invalid-token')->deleteJson('/api/subscriptions/'.$subscription->id)->assertUnauthorized();
    expect($subscription->fresh())->not->toBeNull();
});
it('preserves source subscriber and setup date through partial updates', function () {
    $subscription = app(SaveSubscription::class)(['server' => 'cloud01', 'name' => 'source.de', 'subscriber' => 'Subscriber Company', 'setup_date' => '2020-01-15']);
    app(SaveSubscription::class)(['description' => 'retained'], $subscription);
    expect($subscription->fresh()->subscriber)->toBe('Subscriber Company')->and($subscription->fresh()->setup_date->toDateString())->toBe('2020-01-15');
});
it('returns linked customer and service billing settings', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->create(['customer_id' => $customer->id]);
    $subscription = Subscription::factory()->create(['customer_id' => $customer->id, 'customer_service_id' => $service->id]);
    $this->withToken(User::factory()->create()->createToken('API')->plainTextToken)->getJson('/api/subscriptions/'.$subscription->id)->assertOk()->assertJsonPath('data.customer.name', $customer->displayName())->assertJsonPath('data.customer_service.id', $service->id)->assertJsonPath('data.customer_service.do_not_bill', false);
});
it('rejects explicit null status without reactivating subscriptions through REST', function (string $status) {
    $subscription = Subscription::factory()->create(['status' => $status]);
    $this->withToken(User::factory()->create()->createToken('API')->plainTextToken);
    $this->patchJson('/api/subscriptions/'.$subscription->id, ['status' => null])->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($subscription->fresh()->status->value)->toBe($status);
    $this->patchJson('/api/subscriptions/'.$subscription->id, ['description' => 'Omitted status'])->assertOk()->assertJsonPath('data.status', $status);
})->with(['suspended', 'archived']);
it('rejects explicit null status through MCP while preserving omission defaults', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create(['status' => 'suspended']);
    PortalServer::actingAs($user)->tool(SubscriptionSpeichern::class, ['id' => $subscription->id, 'status' => null])->assertHasErrors();
    expect($subscription->fresh()->status->value)->toBe('suspended');
    PortalServer::actingAs($user)->tool(SubscriptionSpeichern::class, ['id' => $subscription->id, 'description' => 'Omitted status'])->assertOk()->assertSee('suspended');
    $created = app(SaveSubscription::class)(['server' => 'cloud01', 'name' => 'default-status.de']);
    expect($created->status->value)->toBe('active');
});
