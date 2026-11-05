<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Tickets per Schnittstelle — etwa aus einer Ueberwachung oder einem
 * Formular auf der Kundenseite.
 *
 * Ein per API eroeffnetes Ticket hat keinen Absender ("System"), damit die
 * Benachrichtigung beim Team landet und nicht beim Kunden. Antworten ueber
 * `messages` schreibt dagegen der Benutzer, dem das Token gehoert.
 */
class TicketController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'open' => ['nullable', 'boolean'],
            'customer_id' => ['nullable', 'integer'],
            'external_reference' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tickets = Ticket::query()
            ->with('customer')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($request->boolean('open'), fn ($query) => $query->open())
            ->when($filters['customer_id'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['external_reference'] ?? null, fn ($query, string $reference) => $query->where('external_reference', $reference))
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function store(Request $request, CreateTicket $createTicket): JsonResponse
    {
        $request->validate([
            'customer_id' => ['required_without:customer_number', 'nullable', 'integer'],
            'customer_number' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
        ]);

        $customer = Customer::query()
            ->when($request->filled('customer_id'),
                fn ($query) => $query->whereKey($request->integer('customer_id')),
                fn ($query) => $query->where('customer_number', $request->string('customer_number')->toString()))
            ->first();

        abort_if($customer === null, 422, 'Der Kunde wurde nicht gefunden.');
        abort_if($customer->isArchived(), 422, 'Für archivierte Kunden können keine Tickets eröffnet werden.');

        $ticket = $createTicket(
            $customer,
            $request->only(['subject', 'body', 'priority', 'project_id', 'external_reference']),
            TicketSource::Api,
        );

        return (new TicketResource($ticket->load(['customer', 'messages'])))
            ->response()
            ->setStatusCode($ticket->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource($ticket->load(['customer', 'messages.author']));
    }

    public function update(Request $request, Ticket $ticket, UpdateTicket $updateTicket): TicketResource
    {
        $updateTicket($ticket, $request->only(['subject', 'status', 'priority', 'assigned_user_id', 'project_id']));

        return new TicketResource($ticket->refresh()->load('customer'));
    }

    public function storeMessage(Request $request, Ticket $ticket, AddTicketMessage $addTicketMessage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $message = $addTicketMessage($ticket, $request->input('body'), $user, $request->boolean('is_internal'));

        return (new TicketMessageResource($message->load('author')))->response()->setStatusCode(201);
    }
}
