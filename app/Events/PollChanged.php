<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PollChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance for a poll renamed, closed, reopened, deleted or with changed options (F-08, F-21).
     */
    public function __construct(
        public int $pollId,
        public bool $deleted = false,
    ) {}

    /**
     * Get the channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('poll.'.$this->pollId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'poll.changed';
    }

    /**
     * Get the ids the client needs to reload the poll or leave it once deleted.
     *
     * @return array{poll_id: int, deleted: bool}
     */
    public function broadcastWith(): array
    {
        return [
            'poll_id' => $this->pollId,
            'deleted' => $this->deleted,
        ];
    }
}
