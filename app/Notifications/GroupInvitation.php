<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Group;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class GroupInvitation extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell a person they were invited to a group (F-04, F-23).
     */
    public function __construct(
        private readonly Group $group,
        private readonly User $inviter,
        private readonly User $invitee,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Invited;
    }

    public function subject(): Group
    {
        return $this->group;
    }

    public function actor(): User
    {
        return $this->inviter;
    }

    /**
     * Only the invited person.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return collect([$this->invitee]);
    }

    /**
     * @return array{name: string, group: string}
     */
    public function params(): array
    {
        return ['name' => $this->actorName(), 'group' => $this->group->name];
    }
}
