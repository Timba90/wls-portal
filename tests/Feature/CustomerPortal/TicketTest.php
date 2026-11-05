<?php

use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\CreateTicket;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Livewire\CustomerPortal\TicketDetail as PortalTicketDetail;
use App\Livewire\CustomerPortal\TicketForm;
use App\Livewire\Tickets\TicketDetail;
use App\Models\Customer;
use App\Models\PortalUser;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\Tickets\TicketActivity;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
    $this->verantwortlich = User::factory()->create();
    $this->kunde = Customer::factory()->create(['responsible_user_id' => $this->verantwortlich->id]);
    $this->zugang = PortalUser::factory()->for($this->kunde)->create();
});

it('eroeffnet ein Ticket aus dem Kundenportal und benachrichtigt das Team', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();
    $this->actingAs($this->zugang, 'customer');

    Livewire::test(TicketForm::class)
        ->set('subject', 'Kontaktformular defekt')
        ->set('body', 'Seit heute kommen keine Mails mehr an.')
        ->set('priority', 'high')
        ->set('projectId', (string) $projekt->id)
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::query()->sole();

    expect($ticket->ticket_number)->toBe('TK-00001')
        ->and($ticket->customer_id)->toBe($this->kunde->id)
        ->and($ticket->portal_user_id)->toBe($this->zugang->id)
        ->and($ticket->project_id)->toBe($projekt->id)
        ->and($ticket->source)->toBe(TicketSource::Portal)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->messages()->sole()->isFromCustomer())->toBeTrue();

    Notification::assertSentTo($this->verantwortlich, TicketActivity::class, fn (TicketActivity $mail): bool => $mail->opened);
    Notification::assertNotSentTo($this->zugang, TicketActivity::class);
});

it('laesst kein Projekt eines fremden Kunden zu', function (): void {
    $fremdesProjekt = Project::factory()->create();

    expect(fn () => app(CreateTicket::class)($this->kunde, [
        'subject' => 'Test', 'body' => 'Test', 'project_id' => $fremdesProjekt->id,
    ], TicketSource::Portal, $this->zugang))->toThrow(ValidationException::class);
});

it('zeigt im Portal nur eigene Tickets und keine internen Notizen', function (): void {
    $ticket = Ticket::factory()->for($this->kunde)->create(['subject' => 'Eigenes Ticket']);
    TicketMessage::factory()->for($ticket)->create(['body' => 'Öffentliche Antwort']);
    TicketMessage::factory()->for($ticket)->fromTeam()->internal()->create(['body' => 'Geheime interne Notiz']);
    $fremd = Ticket::factory()->create(['subject' => 'Fremdes Ticket']);

    $this->actingAs($this->zugang, 'customer');

    $this->get(route('customer-portal.tickets.index'))
        ->assertOk()
        ->assertSee('Eigenes Ticket')
        ->assertDontSee('Fremdes Ticket');

    $this->get(route('customer-portal.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Öffentliche Antwort')
        ->assertDontSee('Geheime interne Notiz');

    $this->get(route('customer-portal.tickets.show', $fremd))->assertNotFound();
});

it('oeffnet ein geloestes Ticket wieder, wenn der Kunde antwortet', function (): void {
    $ticket = Ticket::factory()->for($this->kunde)->create(['status' => TicketStatus::Resolved]);
    $this->actingAs($this->zugang, 'customer');

    Livewire::test(PortalTicketDetail::class, ['ticket' => $ticket])
        ->set('reply', 'Leider doch noch nicht behoben.')
        ->call('sendReply')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
    Notification::assertSentTo($this->verantwortlich, TicketActivity::class);
});

it('laesst den Kunden ein Ticket als erledigt markieren', function (): void {
    $ticket = Ticket::factory()->for($this->kunde)->create();
    $this->actingAs($this->zugang, 'customer');

    Livewire::test(PortalTicketDetail::class, ['ticket' => $ticket])->call('markResolved');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});

it('beantwortet ein Ticket intern und benachrichtigt den Kunden', function (): void {
    $ticket = Ticket::factory()->for($this->kunde)->create(['portal_user_id' => $this->zugang->id]);
    $this->actingAs($this->verantwortlich);

    Livewire::test(TicketDetail::class, ['ticket' => $ticket])
        ->set('reply', 'Wir schauen uns das an.')
        ->call('sendReply')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::WaitingForCustomer);
    Notification::assertSentTo($this->zugang, TicketActivity::class);
});

it('benachrichtigt den Kunden nicht ueber interne Notizen', function (): void {
    $ticket = Ticket::factory()->for($this->kunde)->create(['portal_user_id' => $this->zugang->id]);
    $this->actingAs($this->verantwortlich);

    Livewire::test(TicketDetail::class, ['ticket' => $ticket])
        ->set('reply', 'Nur fürs Team.')
        ->set('internal', true)
        ->call('sendReply');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->messages()->sole()->is_internal)->toBeTrue();
    Notification::assertNothingSent();
});

it('erlaubt Kunden keine internen Notizen', function (): void {
    $ticket = Ticket::factory()->for($this->kunde)->create();

    expect(fn () => app(AddTicketMessage::class)($ticket, 'Hallo', $this->zugang, isInternal: true))
        ->toThrow(ValidationException::class);
});

it('bearbeitet Status, Zustaendigkeit und Prioritaet intern', function (): void {
    $ticket = Ticket::factory()->for($this->kunde)->create();
    $this->actingAs($this->verantwortlich);

    Livewire::test(TicketDetail::class, ['ticket' => $ticket])
        ->call('assignToMe')
        ->assertSet('status', 'in_progress')
        ->set('status', 'closed')
        ->set('priority', 'urgent')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $ticket->refresh();
    expect($ticket->assigned_user_id)->toBe($this->verantwortlich->id)
        ->and($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->auditLogs()->where('user_id', $this->verantwortlich->id)->exists())->toBeTrue();
});

it('zeigt die interne Ticketliste', function (): void {
    Ticket::factory()->for($this->kunde)->create(['subject' => 'Bitte Postfach anlegen']);
    $this->actingAs($this->verantwortlich);

    $this->get(route('tickets.index'))->assertOk()->assertSee('Bitte Postfach anlegen');
    $this->get(route('tickets.index', ['ansicht' => 'erledigt']))->assertOk()->assertDontSee('Bitte Postfach anlegen');
});

it('schreibt Aktionen von Kundenzugaengen nicht einem internen Benutzer zu', function (): void {
    // Gleiche ID wie der Kundenzugang — sie darf nicht in der Historie landen.
    $this->actingAs($this->zugang, 'customer');

    app(CreateTicket::class)($this->kunde, ['subject' => 'Test', 'body' => 'Test'], TicketSource::Portal, $this->zugang);

    expect(Ticket::query()->sole()->auditLogs()->sole()->user_id)->toBeNull();
});
