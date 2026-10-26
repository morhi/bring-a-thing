<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Poll;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class PollCreated extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell the poll's audience that a new poll waits for their response (F-06, F-12, F-23).
     */
    public function __construct(
        private readonly Poll $poll,
        private readonly User $organizer,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::NewPoll;
    }

    public function subject(): Poll
    {
        return $this->poll;
    }

    public function actor(): User
    {
        return $this->organizer;
    }

    /**
     * The poll's audience: every member, or on a follow-up poll only the source event's accepted participants and managers.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return $this->poll->audience();
    }

    /**
     * @return array{name: string, title: string}
     */
    public function params(): array
    {
        return ['name' => $this->actorName(), 'title' => $this->poll->title];
    }
}
