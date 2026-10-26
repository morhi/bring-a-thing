<?php

namespace App\Data;

use App\Models\Event;
use Spatie\LaravelData\Data;

class EventDeleteCountsData extends Data
{
    public function __construct(
        public int $lists,
        public int $follow_up_polls,
        public int $child_events,
    ) {}

    /**
     * Count what deleting the event removes with it, for the delete confirmation (F-10).
     *
     * Counts everything attached, also what the viewer cannot see themselves.
     */
    public static function fromEvent(Event $event): self
    {
        return new self(
            lists: $event->rosters->count(),
            follow_up_polls: $event->polls->count(),
            child_events: $event->childEvents->count(),
        );
    }
}
