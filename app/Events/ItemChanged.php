<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ItemChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance for an item added, edited or removed on a list (F-14, F-21).
     */
    public function __construct(
        public int $rosterId,
        public int $itemId,
        public bool $deleted = false,
    ) {}

    /**
     * Get the channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('roster.'.$this->rosterId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'item.changed';
    }

    /**
     * Get the ids the client needs to reload the list.
     *
     * @return array{roster_id: int, item_id: int, deleted: bool}
     */
    public function broadcastWith(): array
    {
        return [
            'roster_id' => $this->rosterId,
            'item_id' => $this->itemId,
            'deleted' => $this->deleted,
        ];
    }
}
