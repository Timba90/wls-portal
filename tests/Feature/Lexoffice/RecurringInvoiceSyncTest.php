<?php

use App\Actions\Lexoffice\SyncLexofficeRecurringTemplates;
use App\Enums\LexofficeExecutionInterval;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\LexofficeRecurringTemplate;
use App\Models\LexofficeRecurringTemplateItem;
use App\Support\Lexoffice\LexofficeClient;
use App\Support\Lexoffice\LexofficeException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.lexoffice.api_key' => 'test-lexoffice-key',
        'services.lexoffice.request_interval_ms' => 0,
    ]);
});

/**
 * @param  array<int, array<string, mixed>>  $lineItems
 * @return array<string, mixed>
 */
function lexofficeTemplate(string $id, string $contactId, string $name, array $lineItems = [], string $interval = 'MONTHLY', string $updated = '2026-10-29T16:39:14.119+01:00'): array
{
    $net = collect($lineItems)->whereNotNull('unitPrice')->sum(fn (array $line): float => $line['quantity'] * $line['unitPrice']['netAmount']);

    return [
        'id' => $id,
        'updatedDate' => $updated,
        'title' => 'Rechnung',
        'address' => ['contactId' => $contactId, 'name' => $name],
        'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => $net, 'totalGrossAmount' => round($net * 1.19, 2)],
        'taxConditions' => ['taxType' => 'net'],
        'lineItems' => $lineItems,
        'recurringTemplateSettings' => [
            'startDate' => '2026-10-31',
            'executionInterval' => $interval,
            'nextExecutionDate' => '2026-11-30',
            'lastExecutionDate' => '2026-10-31',
            'lastExecutionFailed' => false,
            'executionStatus' => 'ACTIVE',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function lexofficeLine(string $name, float $netAmount, float $quantity = 1, ?string $id = null): array
{
    return [
        'id' => $id ?? (string) Str::uuid(),
        'type' => 'custom',
        'name' => $name,
        'quantity' => $quantity,
        'unitName' => 'Monate',
        'unitPrice' => ['currency' => 'EUR', 'netAmount' => $netAmount, 'grossAmount' => round($netAmount * 1.19, 2), 'taxRatePercentage' => 19],
        'discountPercentage' => 0,
        'lineItemAmount' => $netAmount * $quantity,
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $templates
 */
function fakeLexoffice(array $templates): void
{
    $byId = collect($templates)->keyBy('id');

    Http::fake(function (Request $request) use ($templates, $byId) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/v1/recurring-templates') {
            return Http::response([
                'content' => array_map(fn (array $template): array => Arr::except($template, ['lineItems', 'taxConditions']), $templates),
                'last' => true,
                'number' => 0,
            ]);
        }

        $id = str_replace('/v1/recurring-templates/', '', (string) $path);

        return $byId->has($id) ? Http::response($byId->get($id)) : Http::response([], 404);
    });
}

it('imports recurring templates with their line items', function () {
    fakeLexoffice([
        lexofficeTemplate('tpl-1', 'contact-1', 'allfo GmbH & Co. KG', [
            lexofficeLine('Webspace Advanced Paket', 70),
            ['type' => 'text', 'name' => 'Hinweis', 'description' => 'Nur Text'],
        ]),
    ]);

    $result = app(SyncLexofficeRecurringTemplates::class)->handle();

    expect($result['templates'])->toBe(1)->and($result['fetched'])->toBe(1);

    $template = LexofficeRecurringTemplate::query()->sole();
    expect($template->contact_name)->toBe('allfo GmbH & Co. KG')
        ->and($template->execution_interval)->toBe(LexofficeExecutionInterval::Monthly)
        ->and($template->total_net_cents)->toBe(7000)
        ->and($template->items)->toHaveCount(1)
        ->and($template->items->first()->line_net_cents)->toBe(7000);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-lexoffice-key'));
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

it('links a uniquely named customer and matches the line item by billing label', function () {
    $customer = Customer::factory()->create(['company_name' => 'allfo GmbH & Co. KG']);
    $service = CustomerService::factory()->for($customer)->create([
        'name' => 'Hosting allfo',
        'billing_label' => 'Webspace Advanced Paket',
        'sales_price_cents' => 7000,
    ]);

    fakeLexoffice([lexofficeTemplate('tpl-1', 'contact-1', 'allfo GmbH & Co. KG', [lexofficeLine('Webspace Advanced Paket', 70)])]);

    $result = app(SyncLexofficeRecurringTemplates::class)->handle();

    expect($result['linked_customers'])->toBe(1)
        ->and($result['matched_items'])->toBe(1)
        ->and($customer->fresh()->lexoffice_contact_id)->toBe('contact-1')
        ->and(LexofficeRecurringTemplate::query()->sole()->customer_id)->toBe($customer->id);

    $item = LexofficeRecurringTemplateItem::query()->sole();
    expect($item->customer_service_id)->toBe($service->id)
        ->and($item->match_source)->toBe(LexofficeRecurringTemplateItem::MatchedAutomatically);
});

it('links private customers despite the salutation lexoffice prepends', function () {
    $customer = Customer::factory()->privatePerson()->create(['first_name' => 'Kristin', 'last_name' => 'Duvinage']);

    fakeLexoffice([lexofficeTemplate('tpl-1', 'contact-1', 'Frau Kristin Duvinage', [lexofficeLine('Domain', 5.33)])]);

    app(SyncLexofficeRecurringTemplates::class)->handle();

    expect($customer->fresh()->lexoffice_contact_id)->toBe('contact-1');
});

it('leaves ambiguous customer names for a manual decision', function () {
    Customer::factory()->count(2)->create(['company_name' => 'Muster GmbH']);

    fakeLexoffice([lexofficeTemplate('tpl-1', 'contact-1', 'Muster GmbH', [lexofficeLine('Hosting', 10)])]);

    $result = app(SyncLexofficeRecurringTemplates::class)->handle();

    expect($result['linked_customers'])->toBe(0)
        ->and(Customer::query()->whereNotNull('lexoffice_contact_id')->count())->toBe(0)
        ->and(LexofficeRecurringTemplate::query()->sole()->customer_id)->toBeNull();
});

it('matches by amount and interval when the names differ but only one service fits', function () {
    $customer = Customer::factory()->create(['company_name' => 'Beispiel AG', 'lexoffice_contact_id' => 'contact-1']);
    $service = CustomerService::factory()->for($customer)->yearly()->create(['name' => 'Wartung', 'sales_price_cents' => 12000]);
    CustomerService::factory()->for($customer)->create(['name' => 'Hosting', 'sales_price_cents' => 12000]);

    fakeLexoffice([lexofficeTemplate('tpl-1', 'contact-1', 'Beispiel AG', [lexofficeLine('Jährliche Pflege', 120)], 'ANNUALLY')]);

    app(SyncLexofficeRecurringTemplates::class)->handle();

    expect(LexofficeRecurringTemplateItem::query()->sole()->customer_service_id)->toBe($service->id);
});

it('skips unchanged templates and keeps manual assignments when items are reread', function () {
    $customer = Customer::factory()->create(['company_name' => 'Beispiel AG', 'lexoffice_contact_id' => 'contact-1']);
    $service = CustomerService::factory()->for($customer)->create(['name' => 'Etwas ganz anderes', 'sales_price_cents' => 999]);

    fakeLexoffice([lexofficeTemplate('tpl-1', 'contact-1', 'Beispiel AG', [lexofficeLine('Hosting', 10, id: 'line-1')])]);
    app(SyncLexofficeRecurringTemplates::class)->handle();

    LexofficeRecurringTemplateItem::query()->sole()->update([
        'customer_service_id' => $service->id,
        'match_source' => LexofficeRecurringTemplateItem::MatchedManually,
    ]);

    // Unveraendert: nur die Liste wird gelesen.
    $result = app(SyncLexofficeRecurringTemplates::class)->handle();
    expect($result['fetched'])->toBe(0);

    // Geaendert: Positionen werden neu gelesen, die Zuordnung bleibt.
    fakeLexoffice([lexofficeTemplate('tpl-1', 'contact-1', 'Beispiel AG', [lexofficeLine('Hosting', 12, id: 'line-1')], updated: '2026-11-10T10:00:00.000+01:00')]);
    $result = app(SyncLexofficeRecurringTemplates::class)->handle();

    $item = LexofficeRecurringTemplateItem::query()->sole();
    expect($result['fetched'])->toBe(1)
        ->and($item->line_net_cents)->toBe(1200)
        ->and($item->customer_service_id)->toBe($service->id)
        ->and($item->match_source)->toBe(LexofficeRecurringTemplateItem::MatchedManually);
});

it('removes templates that no longer exist in lexoffice', function () {
    LexofficeRecurringTemplate::factory()->has(LexofficeRecurringTemplateItem::factory(), 'items')->create(['lexoffice_id' => 'gone']);

    fakeLexoffice([]);
    $result = app(SyncLexofficeRecurringTemplates::class)->handle();

    expect($result['removed'])->toBe(1)
        ->and(LexofficeRecurringTemplate::query()->count())->toBe(0)
        ->and(LexofficeRecurringTemplateItem::query()->count())->toBe(0);
});

it('reports a rejected api key and remembers the failure', function () {
    Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

    expect(fn () => app(SyncLexofficeRecurringTemplates::class)->handle())
        ->toThrow(LexofficeException::class, 'Lexoffice hat den API-Schlüssel abgelehnt.');

    expect(Cache::get(SyncLexofficeRecurringTemplates::LastRunCacheKey)['error'])
        ->toBe('Lexoffice hat den API-Schlüssel abgelehnt.');
});

it('does nothing from the command without an api key', function () {
    config(['services.lexoffice.api_key' => null]);
    Http::fake();

    expect(app(LexofficeClient::class)->isConfigured())->toBeFalse();

    $this->artisan('lexoffice:serienrechnungen')->assertSuccessful();
    Http::assertNothingSent();
});

it('runs the sync from the command', function () {
    fakeLexoffice([lexofficeTemplate('tpl-1', 'contact-1', 'Beispiel AG', [lexofficeLine('Hosting', 10)])]);

    $this->artisan('lexoffice:serienrechnungen')->assertSuccessful();

    expect(LexofficeRecurringTemplate::query()->count())->toBe(1);
});
