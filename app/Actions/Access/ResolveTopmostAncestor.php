<?php

namespace App\Actions\Access;

use App\Models\Event;
use App\Models\Poll;

class ResolveTopmostAncestor
{
    /**
     * Find the topmost ancestor of an event or poll (F-26, F-27).
     *
     * A follow-up poll leads to its source event, a child event to its
     * parent event, an event fixed from a poll to that poll. The walk goes
     * on until an item has no parent and returns the item itself when it
     * already is the topmost one.
     */
    public function handle(Event|Poll $item): Event|Poll
    {
        while (($parent = $this->parent($item)) !== null) {
            $item = $parent;
        }

        return $item;
    }

    private function parent(Event|Poll $item): Event|Poll|null
    {
        if ($item instanceof Poll) {
            return $item->event_id !== null ? $item->event : null;
        }

        if ($item->parent_event_id !== null) {
            return $item->parentEvent;
        }

        return $item->origin_poll_option_id !== null ? $item->originPollOption?->poll : null;
    }
}
