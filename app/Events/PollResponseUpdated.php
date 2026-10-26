<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PollResponseUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance for a response saved on a poll option (F-06, F-21).
     */
    public function __construct(
        public int $pollId,
        public int $optionId,
        public int $userId,
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
        return 'poll.response.updated';
    }

    /**
     * Get the ids the client needs to reload the response grid.
     *
     * @return array{poll_id: int, option_id: int, user_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'poll_id' => $this->pollId,
            'option_id' => $this->optionId,
            'user_id' => $this->userId,
        ];
    }
}
