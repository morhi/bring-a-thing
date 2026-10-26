<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class OptionFixed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance for options fixed as events, which also closes the poll (F-09, F-21).
     *
     * @param  array<int, int>  $optionIds
     * @param  array<int, int>  $eventIds
     */
    public function __construct(
        public int $pollId,
        public array $optionIds,
        public array $eventIds,
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
        return 'poll.option.fixed';
    }

    /**
     * Get the ids the client needs to reload the poll and highlight the fixed options.
     *
     * @return array{poll_id: int, option_ids: array<int, int>, event_ids: array<int, int>}
     */
    public function broadcastWith(): array
    {
        return [
            'poll_id' => $this->pollId,
            'option_ids' => $this->optionIds,
            'event_ids' => $this->eventIds,
        ];
    }
}
