<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ParticipationChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance for a participation status set or removed (F-11, F-21).
     */
    public function __construct(
        public int $eventId,
        public int $userId,
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
        return 'participation.changed';
    }

    /**
     * Get the ids the client needs to reload the participation block.
     *
     * @return array{event_id: int, user_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'user_id' => $this->userId,
        ];
    }
}
