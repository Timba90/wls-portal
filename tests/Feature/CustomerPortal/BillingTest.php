<?php

use App\Actions\Flux\SaveFluxCredentials;
use App\Livewire\CustomerPortal\SepaMandatePage;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PortalUser;
use App\Models\User;
use App\Support\Iban;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Seite einer Flux-Indexantwort.
 *
 * @param  list<array<string, mixed>>  $eintraege
 * @return array<string, mixed>
 */
function fluxSeite(array $eintraege): array
{
    return ['status' => 200, 'data' => ['data' => $eintraege, 'current_page' => 1, 'last_page' => 1]];
}

beforeEach(function (): void {
    app(SaveFluxCredentials::class)->handle(['base_url' => 'https://flux.weblab-studio.de/api', 'token' => 'test-secret', 'tenant_id' => '1'], User::factory()->create()->id);
    config(['portal.customer_portal.sepa.creditor_id' => 'DE98ZZZ09999999999']);

    $this->kunde = Customer::factory()->company()->create(['flux_contact_id' => 190]);
    $this->zugang = PortalUser::factory()->for($this->kunde)->create();

    $this->rechnung = [
        'id' => 7888, 'contact_id' => 190, 'invoice_number' => '2026-00004263', 'invoice_date' => '2026-11-03',
        'payment_target_date' => '2026-11-17', 'total_net_price' => '744.0000000000', 'total_gross_price' => '885.3600000000',
        'balance' => '885.3600000000', 'payment_state' => 'open',
    ];
});

it('zeigt die Rechnungen des Kunden und filtert fremde heraus', function (): void {
    Http::fake(['flux.weblab-studio.de/api/orders*' => Http::response(fluxSeite([
        $this->rechnung,
        [...$this->rechnung, 'id' => 7000, 'invoice_number' => '2026-00000001', 'payment_state' => 'paid', 'balance' => '0'],
        [...$this->rechnung, 'id' => 7001, 'contact_id' => 255, 'invoice_number' => 'FREMD-0001'],
        [...$this->rechnung, 'id' => 7002, 'invoice_number' => null],
    ]))]);

    $this->actingAs($this->zugang, 'customer')
        ->get(route('customer-portal.invoices.index'))
        ->assertOk()
        ->assertSee('2026-00004263')
        ->assertSee('2026-00000001')
        ->assertSee('885,36 €')
        ->assertDontSee('FREMD-0001');

    Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'filter[contact_id]=190'));
});

it('zeigt einen Hinweis statt eines Fehlers, wenn Flux nicht antwortet', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $this->actingAs($this->zugang, 'customer')
        ->get(route('customer-portal.invoices.index'))
        ->assertOk()
        ->assertSee('können gerade nicht abgerufen werden');

    $this->get(route('customer-portal.dashboard'))->assertOk()->assertSee('Gerade nicht abrufbar');
});

it('zeigt ohne Flux-Verknuepfung einen Hinweis und fragt Flux nicht', function (): void {
    Http::fake();
    $this->kunde->update(['flux_contact_id' => null]);

    $this->actingAs($this->zugang, 'customer')
        ->get(route('customer-portal.invoices.index'))
        ->assertOk()
        ->assertSee('noch nicht eingerichtet');

    Http::assertNothingSent();
});

it('liefert das PDF einer eigenen Rechnung aus', function (): void {
    Http::fake([
        'flux.weblab-studio.de/api/orders/7888*' => Http::response(['status' => 200, 'data' => [
            ...$this->rechnung,
            'media' => [
                ['id' => 14000, 'collection_name' => 'attachments'],
                ['id' => 14116, 'collection_name' => 'invoice'],
            ],
        ]]),
        'flux.weblab-studio.de/api/media/private/14116' => Http::response('%PDF-1.7 Inhalt', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $antwort = $this->actingAs($this->zugang, 'customer')
        ->get(route('customer-portal.invoices.download', 7888))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($antwort->getContent())->toBe('%PDF-1.7 Inhalt')
        ->and($antwort->headers->get('Content-Disposition'))->toContain('Rechnung-2026-00004263.pdf');
});

it('liefert keine Rechnung eines anderen Flux-Kontakts aus', function (): void {
    Http::fake([
        'flux.weblab-studio.de/api/orders/7001*' => Http::response(['status' => 200, 'data' => [
            ...$this->rechnung, 'id' => 7001, 'contact_id' => 255,
            'media' => [['id' => 15000, 'collection_name' => 'invoice']],
        ]]),
        '*' => Http::response('%PDF fremd'),
    ]);

    $this->actingAs($this->zugang, 'customer')
        ->get(route('customer-portal.invoices.download', 7001))
        ->assertNotFound();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'media/private'));
});

it('zeigt bestehende Mandate nur mit maskierter IBAN', function (): void {
    Http::fake([
        'flux.weblab-studio.de/api/contact-bank-connections*' => Http::response(fluxSeite([
            ['id' => 5, 'contact_id' => 190, 'iban' => 'DE89370400440532013000', 'account_holder' => 'Muster GmbH', 'bank_name' => 'Commerzbank'],
        ])),
        'flux.weblab-studio.de/api/sepa-mandates*' => Http::response(fluxSeite([
            ['id' => 9, 'contact_id' => 190, 'contact_bank_connection_id' => 5, 'mandate_reference_number' => '36845', 'signed_date' => '2026-02-12', 'sepa_mandate_type_enum' => 'BASIC'],
        ])),
    ]);

    $this->actingAs($this->zugang, 'customer')
        ->get(route('customer-portal.sepa-mandate'))
        ->assertOk()
        ->assertSee('36845')
        ->assertSee('DE89 **** **** 3000')
        ->assertDontSee('DE89370400440532013000');
});

it('erteilt ein SEPA-Mandat in Flux', function (): void {
    Http::fake([
        'flux.weblab-studio.de/api/contact-bank-connections*' => Http::sequence()
            ->push(fluxSeite([]))
            ->push(['status' => 201, 'data' => ['id' => 6, 'contact_id' => 190, 'iban' => 'DE89370400440532013000']], 201)
            ->push(fluxSeite([])),
        'flux.weblab-studio.de/api/sepa-mandates*' => Http::sequence()
            ->push(fluxSeite([]))
            ->push(['status' => 201, 'data' => ['id' => 10, 'contact_id' => 190, 'mandate_reference_number' => '36846']], 201)
            ->push(fluxSeite([])),
    ]);

    $this->actingAs($this->zugang, 'customer');

    Livewire::test(SepaMandatePage::class)
        ->set('accountHolder', 'Muster GmbH')
        ->set('iban', 'de89 3704 0044 0532 0130 00')
        ->set('type', 'B2B')
        ->set('consent', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('createdReference', '36846');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), 'contact-bank-connections')
        && $request['iban'] === 'DE89370400440532013000'
        && $request['contact_id'] === 190);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), 'sepa-mandates')
        && $request['contact_bank_connection_id'] === 6
        && $request['tenant_id'] === 1
        && $request['sepa_mandate_type_enum'] === 'B2B'
        && $request['signed_date'] === now()->toDateString());

    $eintrag = AuditLog::query()->where('auditable_id', $this->kunde->id)->latest('id')->first();
    expect($eintrag->description)->toContain('36846')->toContain('DE89 **** **** 3000')->not->toContain('0532013000');
});

it('prueft IBAN und Zustimmung, bevor etwas an Flux geht', function (array $eingabe, string $fehler): void {
    Http::fake(['*' => Http::response(fluxSeite([]))]);
    $this->actingAs($this->zugang, 'customer');

    Livewire::test(SepaMandatePage::class)
        ->set('accountHolder', 'Muster GmbH')
        ->set('iban', $eingabe['iban'])
        ->set('consent', $eingabe['consent'])
        ->call('submit')
        ->assertHasErrors($fehler);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
})->with([
    'falsche Pruefziffer' => [['iban' => 'DE89370400440532013001', 'consent' => true], 'iban'],
    'ohne Zustimmung' => [['iban' => 'DE89370400440532013000', 'consent' => false], 'consent'],
]);

it('erlaubt Privatkunden kein Firmenlastschrift-Mandat', function (): void {
    Http::fake(['*' => Http::response(fluxSeite([]))]);
    $privat = Customer::factory()->privatePerson()->create(['flux_contact_id' => 191]);
    $this->actingAs(PortalUser::factory()->for($privat)->create(), 'customer');

    Livewire::test(SepaMandatePage::class)
        ->set('accountHolder', 'Max Muster')
        ->set('iban', 'DE89370400440532013000')
        ->set('type', 'B2B')
        ->set('consent', true)
        ->call('submit')
        ->assertHasErrors('type');
});

it('erkennt gueltige und ungueltige IBANs', function (): void {
    expect(Iban::isValid('DE89 3704 0044 0532 0130 00'))->toBeTrue()
        ->and(Iban::isValid('GB82WEST12345698765432'))->toBeTrue()
        ->and(Iban::isValid('DE89370400440532013001'))->toBeFalse()
        ->and(Iban::isValid('DE8937040044053201300'))->toBeFalse()
        ->and(Iban::isValid('kein iban'))->toBeFalse();
});
