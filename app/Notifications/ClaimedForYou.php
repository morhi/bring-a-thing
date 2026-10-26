<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ClaimedForYou extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell a person that someone else claimed an item on their behalf (F-15, F-23).
     */
    public function __construct(
        private readonly RosterItem $item,
        private readonly User $claimant,
        private readonly User $manager,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ClaimedFor;
    }

    public function subject(): Roster
    {
        return $this->item->roster;
    }

    public function actor(): User
    {
        return $this->manager;
    }

    /**
     * Only the person the claim belongs to.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return collect([$this->claimant]);
    }

    /**
     * @return array{name: string, item: string}
     */
    public function params(): array
    {
        return ['name' => $this->actorName(), 'item' => $this->item->name];
    }
}
