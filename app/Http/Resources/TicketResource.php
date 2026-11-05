<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'source' => $this->source->value,
            'external_reference' => $this->external_reference,
            'customer_id' => $this->customer_id,
            'project_id' => $this->project_id,
            'assigned_user_id' => $this->assigned_user_id,
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'name' => $this->customer->displayName(),
                'customer_number' => $this->customer->customer_number,
            ]),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
