<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DateFixed extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell the event's participants that a poll date became this event (F-09, F-23).
     */
    public function __construct(
        private readonly Event $event,
        private readonly User $organizer,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Fixed;
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
     * The participants derived from the responses: "accepted" from yes and "pending" from maybe.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return $this->event->participations()
            ->whereIn('status', [ParticipationStatus::Accepted, ParticipationStatus::Pending])
            ->with('user')
            ->get()
            ->pluck('user');
    }

    /**
     * @return array{name: string, title: string, date: string}
     */
    public function params(): array
    {
        return ['name' => $this->actorName(), 'title' => $this->event->title, 'date' => $this->event->date->format('Y-m-d')];
    }
}
