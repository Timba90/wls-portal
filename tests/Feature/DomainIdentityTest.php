<?php

use App\Actions\Registrar\ImportRegistrarInventory;
use App\Enums\RegistrarProvider;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Support\Registrar\RegistrarClient;
use App\Support\Registrar\RemoteDomain;

it('imports accented and ASCII domains separately and preserves assignments on repeat import', function (): void {
    $names = ['hofats.de', 'höfats.de', 'hofats.com', 'höfats.com'];
    $remote = array_map(fn (string $name, int $index): RemoteDomain => new RemoteDomain($name, 'ref-'.$index, 'active'), $names, array_keys($names));
    $client = Mockery::mock(RegistrarClient::class);
    $client->shouldReceive('isConfigured')->andReturn(true);
    $client->shouldReceive('provider')->andReturn(RegistrarProvider::ResellerInterface);
    $client->shouldReceive('domains')->andReturn($remote);
    $client->shouldReceive('certificates')->andReturn([]);

    $result = app(ImportRegistrarInventory::class)($client);
    expect($result['domains'])->toBe(['new' => 4, 'updated' => 0])->and(Domain::query()->count())->toBe(4);
    $assignments = [];
    foreach ($names as $index => $name) {
        $service = CustomerService::factory()->create();
        $domain = Domain::query()->where('name', $name)->sole();
        $domain->update(['customer_id' => $service->customer_id, 'customer_service_id' => $service->id]);
        $assignments[$name] = [$domain->id, $service->customer_id, $service->id];
    }
    $result = app(ImportRegistrarInventory::class)($client);
    expect($result['domains'])->toBe(['new' => 0, 'updated' => 4]);
    foreach ($names as $index => $name) {
        $domain = Domain::query()->where('name', Domain::normalizeName(mb_strtoupper($name).'.'))->sole();
        expect([$domain->id, $domain->customer_id, $domain->customer_service_id])->toBe($assignments[$name])
            ->and($domain->provider_reference)->toBe('ref-'.$index);
    }
});

it('keeps partial domain searches case and accent insensitive', function (): void {
    Domain::factory()->create(['name' => 'höfats.de']);
    Domain::factory()->create(['name' => 'hofats.com']);
    Domain::factory()->create(['name' => 'other.de']);
    expect(Domain::query()->searchName('HOFATS')->orderBy('name')->pluck('name')->all())->toBe(['hofats.com', 'höfats.de'])
        ->and(Domain::query()->searchName('HÖFATS')->count())->toBe(2);
});

it('refuses a lossy rollback without changing domain records', function (): void {
    $migration = require database_path('migrations/2026_10_21_191802_preserve_binary_domain_name_identity.php');
    Domain::factory()->create(['name' => 'hofats.de']);
    Domain::factory()->create(['name' => 'höfats.de']);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(Domain::query()->count())->toBe(2);
});
