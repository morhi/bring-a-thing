<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use Illuminate\Support\Facades\DB;

class DeleteEvent
{
    /**
     * Delete the event together with its lists, follow-up polls and child events (F-10).
     *
     * Child events go first with everything attached to them. Comments are
     * polymorphic and have no foreign key, so they are removed explicitly;
     * items, claims, options, responses and participations follow through
     * their cascading foreign keys.
     */
    public function handle(Event $event): void
    {
        DB::transaction(function () use ($event) {
            foreach ($event->childEvents as $childEvent) {
                $this->handle($childEvent);
            }

            $event->rosters->each(fn (Roster $roster) => $this->deleteRoster($roster));
            $event->polls->each(fn (Poll $poll) => $poll->delete());
            $event->comments()->delete();
            $event->delete();
        });
    }

    /**
     * Delete a list with the comments on it and on its items.
     */
    private function deleteRoster(Roster $roster): void
    {
        foreach ($roster->items as $item) {
            $item->comments()->delete();
        }

        $roster->comments()->delete();
        $roster->delete();
    }
}
