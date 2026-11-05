<?php

use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Tickets\TicketActivity;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->benutzer = User::factory()->create();
    $this->kunde = Customer::factory()->create(['responsible_user_id' => $this->benutzer->id]);
});

it('verlangt eine Anmeldung', function (): void {
    $this->postJson('/api/tickets', ['customer_id' => $this->kunde->id, 'subject' => 'X', 'body' => 'Y'])->assertUnauthorized();
});

it('eroeffnet ein Ticket per Kundennummer', function (): void {
    $this->withToken($this->benutzer->createToken('Monitoring')->plainTextToken);

    $this->postJson('/api/tickets', [
        'customer_number' => $this->kunde->customer_number,
        'subject' => 'Webseite nicht erreichbar',
        'body' => 'HTTP 503 seit 03:12 Uhr.',
        'priority' => 'urgent',
        'external_reference' => 'uptime-4711',
    ])
        ->assertCreated()
        ->assertJsonPath('data.source', 'api')
        ->assertJsonPath('data.priority', 'urgent')
        ->assertJsonPath('data.customer.customer_number', $this->kunde->customer_number)
        ->assertJsonPath('data.messages.0.author', 'system');

    Notification::assertSentTo($this->benutzer, TicketActivity::class);
});

it('haengt eine wiederholte Meldung mit derselben Referenz an', function (): void {
    $this->withToken($this->benutzer->createToken('Monitoring')->plainTextToken);
    $daten = ['customer_id' => $this->kunde->id, 'subject' => 'Down', 'body' => 'Erste Meldung', 'external_reference' => 'uptime-1'];

    $id = $this->postJson('/api/tickets', $daten)->assertCreated()->json('data.id');
    Ticket::query()->whereKey($id)->update(['status' => TicketStatus::Closed]);

    $this->postJson('/api/tickets', [...$daten, 'body' => 'Zweite Meldung'])
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.status', 'open');

    expect(Ticket::query()->count())->toBe(1)
        ->and(Ticket::query()->sole()->messages()->count())->toBe(2);
});

it('weist unbekannte Kunden und fehlende Pflichtfelder ab', function (): void {
    $this->withToken($this->benutzer->createToken('API')->plainTextToken);

    $this->postJson('/api/tickets', ['customer_number' => 'KD-99999', 'subject' => 'X', 'body' => 'Y'])->assertUnprocessable();
    $this->postJson('/api/tickets', ['customer_id' => $this->kunde->id])->assertUnprocessable()->assertJsonValidationErrors(['subject', 'body']);
});

it('listet, zeigt, aendert und beantwortet Tickets', function (): void {
    $this->withToken($this->benutzer->createToken('API')->plainTextToken);
    $ticket = Ticket::factory()->for($this->kunde)->create(['source' => TicketSource::Portal]);
    Ticket::factory()->closed()->create();

    $this->getJson('/api/tickets?open=1')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/tickets/'.$ticket->id)->assertOk()->assertJsonPath('data.ticket_number', $ticket->ticket_number);

    $this->patchJson('/api/tickets/'.$ticket->id, ['status' => 'in_progress', 'assigned_user_id' => $this->benutzer->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.assigned_user_id', $this->benutzer->id);

    $this->postJson('/api/tickets/'.$ticket->id.'/messages', ['body' => 'Notiz', 'is_internal' => true])
        ->assertCreated()
        ->assertJsonPath('data.author', 'team')
        ->assertJsonPath('data.is_internal', true);
});
