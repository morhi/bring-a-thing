<?php

namespace App\Events;

use App\Models\RosterItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RosterItemSaved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public RosterItem $item,
    ) {
        $this->item->loadMissing(['roster', 'claims.user', 'customFieldValues.customField']);
    }

    /**
     * Get the channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('roster.'.$this->item->roster_id);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'item.saved';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['item' => $this->item->toArray()];
    }
}
