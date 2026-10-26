<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Roster;
use App\Models\RosterItemClaim;
use App\Models\User;
use App\Notifications\Concerns\DeliversInApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ListCommented extends Notification implements InAppNotification
{
    use DeliversInApp;

    /**
     * Tell the list's claimants about a new comment on the list or one of its items (F-20, F-23).
     */
    public function __construct(
        private readonly Roster $roster,
        private readonly User $author,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Comment;
    }

    public function subject(): Roster
    {
        return $this->roster;
    }

    public function actor(): User
    {
        return $this->author;
    }

    /**
     * Everyone with a claim on any item of the list.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::query()
            ->whereIn('id', RosterItemClaim::query()
                ->select('user_id')
                ->whereHas('rosterItem', fn (Builder $item) => $item->where('roster_id', $this->roster->getKey())))
            ->get();
    }

    /**
     * @return array{name: string, list: string}
     */
    public function params(): array
    {
        return ['name' => $this->actorName(), 'list' => $this->roster->title];
    }
}
