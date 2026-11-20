<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Event;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use App\Support\WallClock;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class EventCreated extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell the group, or a standalone event's participants, that an event was created directly and waits for their answer (F-10, F-23).
     */
    public function __construct(
        private readonly Event $event,
        private readonly User $organizer,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::NewEvent;
    }

    public function subject(): Event
    {
        return $this->event;
    }

    public function actor(): User
    {
        return $this->organizer;
    }

    /**
     * Every group member, since each one is asked to accept or decline.
     *
     * A standalone event has no members; it asks only the participants it
     * starts with, such as those of a standalone sub-event's parent (F-29).
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        if ($this->event->group_id === null) {
            return $this->event->participations()->with('user')->get()->pluck('user')->filter()->values();
        }

        return $this->event->group->members()->get();
    }

    /**
     * The date is the event's start day in the recipient's zone (section 9).
     *
     * @return array{name: string, title: string, date: string}
     */
    public function params(?User $notifiable = null): array
    {
        return ['name' => $this->actorName(), 'title' => $this->event->title, 'date' => $this->event->localDate(WallClock::zoneOf($notifiable))->format('Y-m-d')];
    }
}
