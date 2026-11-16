<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Group;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class GroupMembershipRequested extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell the group owner that a guest asks for membership (F-03, F-23).
     */
    public function __construct(
        private readonly Group $group,
        private readonly User $requester,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::GroupMembershipRequested;
    }

    public function subject(): Group
    {
        return $this->group;
    }

    public function actor(): User
    {
        return $this->requester;
    }

    /**
     * Only the group owner, who resolves the request.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return collect([$this->group->owner]);
    }

    /**
     * @return array{name: string, group: string}
     */
    public function params(): array
    {
        return ['name' => $this->actorName(), 'group' => $this->group->name];
    }
}
