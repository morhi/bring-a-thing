<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Event;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class EventCreated extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell the group that an event was created directly and waits for their answer (F-10, F-23).
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
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return $this->event->group->members()->get();
    }

    /**
     * @return array{name: string, title: string, date: string}
     */
    public function params(): array
    {
        return ['name' => $this->actorName(), 'title' => $this->event->title, 'date' => $this->event->date->format('Y-m-d')];
    }
}
