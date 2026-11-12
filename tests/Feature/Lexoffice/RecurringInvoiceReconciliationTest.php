<?php

use App\Actions\Lexoffice\ReconcileRecurringInvoices;
use App\Enums\RecurringInvoiceMatchStatus;
use App\Jobs\SyncLexofficeRecurringTemplatesJob;
use App\Livewire\Billing\RecurringInvoiceReconciliation;
use App\Livewire\System\IntegrationSettings;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\IntegrationCredential;
use App\Models\LexofficeRecurringTemplate;
use App\Models\LexofficeRecurringTemplateItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Eine aktive Vorlage fuer den Kunden mit einer Position, optional zugeordnet.
 */
function billedItem(Customer $customer, int $lineNetCents, ?CustomerService $service = null, string $interval = 'MONTHLY', string $status = 'ACTIVE'): LexofficeRecurringTemplateItem
{
    $template = LexofficeRecurringTemplate::factory()->create([
        'lexoffice_contact_id' => $customer->lexoffice_contact_id,
        'contact_name' => $customer->displayName(),
        'customer_id' => $customer->id,
        'execution_interval' => $interval,
        'execution_status' => $status,
        'total_net_cents' => $lineNetCents,
    ]);

    return LexofficeRecurringTemplateItem::factory()->for($template, 'template')->create([
        'line_net_cents' => $lineNetCents,
        'unit_price_net_cents' => $lineNetCents,
        'customer_service_id' => $service?->id,
        'match_source' => $service ? LexofficeRecurringTemplateItem::MatchedManually : null,
    ]);
}

function statusOf(CustomerService $service): ?RecurringInvoiceMatchStatus
{
    return app(ReconcileRecurringInvoices::class)->handle()['services']
        ->first(fn (array $row): bool => $row['service']->is($service))['status'] ?? null;
}

it('classifies services against their recurring invoices', function () {
    $customer = Customer::factory()->create(['lexoffice_contact_id' => 'contact-1']);

    $matching = CustomerService::factory()->for($customer)->create(['sales_price_cents' => 7000]);
    billedItem($customer, 7000, $matching);

    $cheaper = CustomerService::factory()->for($customer)->create(['sales_price_cents' => 7000]);
    billedItem($customer, 6000, $cheaper);

    $yearlyBilledMonthly = CustomerService::factory()->for($customer)->yearly()->create(['sales_price_cents' => 12000]);
    billedItem($customer, 1000, $yearlyBilledMonthly);

    $missing = CustomerService::factory()->for($customer)->create(['sales_price_cents' => 500]);

    $notBillable = CustomerService::factory()->for($customer)->doNotBill()->create(['sales_price_cents' => 500]);
    billedItem($customer, 500, $notBillable);

    $endedTemplate = CustomerService::factory()->for($customer)->create(['sales_price_cents' => 900]);
    billedItem($customer, 900, $endedTemplate, status: 'ENDED');

    $ignoredEntirely = CustomerService::factory()->for($customer)->doNotBill()->create();

    expect(statusOf($matching))->toBe(RecurringInvoiceMatchStatus::Matching)
        ->and(statusOf($cheaper))->toBe(RecurringInvoiceMatchStatus::AmountDeviation)
        ->and(statusOf($yearlyBilledMonthly))->toBe(RecurringInvoiceMatchStatus::IntervalDeviation)
        ->and(statusOf($missing))->toBe(RecurringInvoiceMatchStatus::NotInvoiced)
        ->and(statusOf($notBillable))->toBe(RecurringInvoiceMatchStatus::InvoicedButNotBillable)
        ->and(statusOf($endedTemplate))->toBe(RecurringInvoiceMatchStatus::NotInvoiced)
        ->and(statusOf($ignoredEntirely))->toBeNull();
});

it('lists open items and unlinked contacts of active templates only', function () {
    $customer = Customer::factory()->create(['lexoffice_contact_id' => 'contact-1']);
    $open = billedItem($customer, 1000);
    billedItem($customer, 1000, status: 'ENDED');
    LexofficeRecurringTemplateItem::factory()->for(
        LexofficeRecurringTemplate::factory()->create(['lexoffice_contact_id' => 'contact-2', 'contact_name' => 'Unbekannt GmbH', 'total_net_cents' => 1000]),
        'template',
    )->create();

    $report = app(ReconcileRecurringInvoices::class)->handle();

    expect($report['open_items']->pluck('id')->all())->toBe([$open->id])
        ->and($report['unlinked_contacts']->pluck('name')->all())->toBe(['Unbekannt GmbH'])
        ->and($report['unlinked_contacts']->first()['yearly']->cents)->toBe(12000);
});

it('protects the page from guests', function () {
    $this->get('/serienrechnungen')->assertRedirect('/login');
});

it('renders the reconciliation page without calling lexoffice', function () {
    Http::fake();
    $customer = Customer::factory()->create(['company_name' => 'Sichtbar GmbH', 'lexoffice_contact_id' => 'contact-1']);
    CustomerService::factory()->for($customer)->create(['name' => 'Fehlende Wartung']);

    $this->actingAs(User::factory()->create())
        ->get('/serienrechnungen?ansicht=fehlend')
        ->assertOk()
        ->assertSee('Serienrechnungen')
        ->assertSee('Fehlende Wartung');

    Http::assertNothingSent();
});

it('links a lexoffice contact to a customer and then matches its items', function () {
    $customer = Customer::factory()->create(['company_name' => 'Anders benannt GmbH']);
    $service = CustomerService::factory()->for($customer)->create(['billing_label' => 'Webspace', 'sales_price_cents' => 7000]);
    $template = LexofficeRecurringTemplate::factory()->create(['lexoffice_contact_id' => 'contact-9', 'contact_name' => 'Alter Name GmbH']);
    $item = LexofficeRecurringTemplateItem::factory()->for($template, 'template')->create(['name' => 'Webspace', 'line_net_cents' => 7000]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecurringInvoiceReconciliation::class)
        ->set('view', 'kunden')
        ->assertSee('Alter Name GmbH')
        ->set('contactLinks.contact-9', $customer->id)
        ->call('linkContact', 'contact-9')
        ->assertHasNoErrors();

    expect($customer->fresh()->lexoffice_contact_id)->toBe('contact-9')
        ->and($template->fresh()->customer_id)->toBe($customer->id)
        ->and($item->fresh()->customer_service_id)->toBe($service->id);
});

it('assigns, releases and ignores items by hand', function () {
    $customer = Customer::factory()->create(['lexoffice_contact_id' => 'contact-1']);
    $service = CustomerService::factory()->for($customer)->create();
    $item = billedItem($customer, 1234);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(RecurringInvoiceReconciliation::class)
        ->set('view', 'positionen')
        ->set("itemAssignments.{$item->id}", $service->id)
        ->call('assignItem', $item->id)
        ->assertHasNoErrors();

    expect($item->fresh()->customer_service_id)->toBe($service->id);

    $component->call('releaseItem', $item->id);
    expect($item->fresh())->customer_service_id->toBeNull()
        ->match_source->toBe(LexofficeRecurringTemplateItem::MatchedManually);

    $component->call('ignoreItem', $item->id);
    expect($item->fresh()->is_ignored)->toBeTrue();

    $component->call('restoreItem', $item->id);
    expect($item->fresh()->is_ignored)->toBeFalse();
});

it('refuses to assign a service of another customer', function () {
    $customer = Customer::factory()->create(['lexoffice_contact_id' => 'contact-1']);
    $foreign = CustomerService::factory()->create();
    $item = billedItem($customer, 1000);

    Livewire::actingAs(User::factory()->create())
        ->test(RecurringInvoiceReconciliation::class)
        ->set("itemAssignments.{$item->id}", $foreign->id)
        ->call('assignItem', $item->id)
        ->assertHasErrors("itemAssignments.{$item->id}");

    expect($item->fresh()->customer_service_id)->toBeNull();
});

it('queues the sync from the page once an api key exists', function () {
    Queue::fake();
    config(['services.lexoffice.api_key' => 'test-lexoffice-key']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecurringInvoiceReconciliation::class)
        ->call('sync');

    Queue::assertPushed(SyncLexofficeRecurringTemplatesJob::class);
});

it('does not queue the sync without an api key', function () {
    Queue::fake();
    config(['services.lexoffice.api_key' => null]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecurringInvoiceReconciliation::class)
        ->call('sync')
        ->assertSee('API-Schlüssel');

    Queue::assertNothingPushed();
});

it('stores the lexoffice api key without echoing it back', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(IntegrationSettings::class)
        ->set('lexofficeApiKey', 'secret-lexoffice-key')
        ->call('saveLexoffice')
        ->assertDispatched('zugang-gespeichert')
        ->assertSet('lexofficeApiKey', '')
        ->assertDontSee('secret-lexoffice-key')
        ->assertSee('Ein API-Schlüssel ist hinterlegt.');

    expect(IntegrationCredential::valuesFor('lexoffice'))->toBe(['api_key' => 'secret-lexoffice-key']);
});
