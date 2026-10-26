<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class EventUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance for an event edited or deleted, or whose lists, follow-up polls or child events changed (F-10, F-21).
     */
    public function __construct(
        public int $eventId,
        public bool $deleted = false,
    ) {}

    /**
     * Get the channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('event.'.$this->eventId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'event.updated';
    }

    /**
     * Get the ids the client needs to reload the event or leave it once deleted.
     *
     * @return array{event_id: int, deleted: bool}
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'deleted' => $this->deleted,
        ];
    }
}
