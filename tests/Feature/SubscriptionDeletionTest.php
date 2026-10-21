<?php

use App\Actions\Subscriptions\DeleteSubscriptions;
use App\Livewire\Subscriptions\SubscriptionList;
use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Subscriptions\SubscriptionLoeschen;
use App\Models\CustomerService;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('deletes only explicit subscriptions and keeps customer services with an audit snapshot', function (): void {
    $service = CustomerService::factory()->create();
    $first = Subscription::factory()->create(['customer_id' => $service->customer_id, 'customer_service_id' => $service->id]);
    $second = Subscription::factory()->create();
    $keep = Subscription::factory()->create();
    $this->actingAs(User::factory()->create());
    expect(app(DeleteSubscriptions::class)([$first->id, $second->id]))->toBe(2);
    $this->assertModelMissing($first);
    $this->assertModelMissing($second);
    $this->assertModelExists($keep);
    $this->assertModelExists($service);
    $this->assertModelExists($service->customer);
    $this->assertDatabaseHas('audit_logs', ['auditable_type' => Subscription::class, 'auditable_id' => $first->id, 'event' => 'deleted', 'user_id' => auth()->id()]);
});

it('rejects invalid or stale batches without partially deleting anything', function (array $ids): void {
    $subscription = Subscription::factory()->create();
    $ids = array_map(fn ($id) => $id === 'existing' ? $subscription->id : $id, $ids);
    expect(fn () => app(DeleteSubscriptions::class)($ids))->toThrow(ValidationException::class);
    $this->assertModelExists($subscription);
})->with([[[]], [['existing', 99999999]], [['existing', 'existing']], [[0]], [['bad']], [range(1, 101)]]);

it('supports authenticated single and bulk API deletion and rejects guests', function (): void {
    $a = Subscription::factory()->create();
    $b = Subscription::factory()->create();
    $this->deleteJson('/api/subscriptions/'.$a->id)->assertUnauthorized();
    $this->postJson('/api/subscriptions/bulk-delete', ['ids' => [$a->id]])->assertUnauthorized();
    $this->withToken(User::factory()->create()->createToken('delete')->plainTextToken);
    $this->postJson('/api/subscriptions/bulk-delete', ['ids' => [$a->id, 99999999]])->assertUnprocessable();
    $this->assertModelExists($a);
    $this->deleteJson('/api/subscriptions/'.$a->id)->assertNoContent();
    $this->postJson('/api/subscriptions/bulk-delete', ['ids' => [$b->id]])->assertOk()->assertJsonPath('deleted', 1);
    expect(Subscription::count())->toBe(0);
});

it('exposes destructive MCP deletion with explicit ids', function (): void {
    $a = Subscription::factory()->create();
    $b = Subscription::factory()->create();
    $user = User::factory()->create();
    PortalServer::actingAs($user)->tool(SubscriptionLoeschen::class, ['ids' => [$a->id, 99999999]])->assertHasErrors();
    $this->assertModelExists($a);
    PortalServer::actingAs($user)->tool(SubscriptionLoeschen::class, ['ids' => [$a->id, $b->id]])->assertOk()->assertSee('2');
    expect(Subscription::count())->toBe(0);
    $tools = $this->withToken($user->createToken('tools')->plainTextToken)->postJson(route('mcp.portal'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->json('result.tools');
    expect(collect($tools)->firstWhere('name', 'subscription-loeschen')['annotations']['destructiveHint'])->toBeTrue();
});

it('requires confirmation and clears selection on filters and pagination', function (): void {
    $a = Subscription::factory()->create(['server' => 'cloud01']);
    $b = Subscription::factory()->create(['server' => 'cloud02']);
    $screen = Livewire::actingAs(User::factory()->create())->test(SubscriptionList::class)
        ->set('selectedIds', [(string) $a->id])->call('deleteSelected')->assertHasErrors('selectedIds');
    $this->assertModelExists($a);
    $screen->call('confirmDeletion')->assertSet('showDeleteConfirmation', true)
        ->assertSee($a->name)->call('deleteSelected')->assertHasNoErrors()->assertSet('selectedIds', []);
    $this->assertModelMissing($a);
    $this->assertModelExists($b);
    $screen->set('selectedIds', [(string) $b->id])->set('server', 'cloud02')->assertSet('selectedIds', [])
        ->call('togglePageSelection')->assertSet('selectedIds', [(string) $b->id])
        ->call('resetFilters')->assertSet('selectedIds', [])
        ->set('selectedIds', [(string) $b->id])->call('setPage', 2)->assertSet('selectedIds', []);
});

it('does not allow selecting hidden pages or filtered records for UI deletion', function (): void {
    $a = Subscription::factory()->create(['server' => 'cloud01']);
    $screen = Livewire::actingAs(User::factory()->create())->test(SubscriptionList::class)
        ->set('server', 'cloud02')->set('selectedIds', [(string) $a->id])
        ->call('confirmDeletion')->assertHasErrors('selectedIds.*');
    $this->assertModelExists($a);
});
