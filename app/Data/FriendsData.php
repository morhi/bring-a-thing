<?php

namespace App\Data;

use App\Models\Friend;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class FriendsData extends Data
{
    /**
     * @param  array<int, FriendData>  $items
     */
    public function __construct(
        public array $items,
    ) {}

    /**
     * Build the friends page of the user (F-05).
     *
     * Named friends come first, then the ones without a name, each sorted
     * alphabetically. Shared groups list the groups both people are in.
     */
    public static function forUser(User $user): self
    {
        /** @var Collection<int, Group> $groups */
        $groups = $user->groups()->with('members')->orderBy('name')->get();

        $items = $user->friends()->get()
            ->map(fn (Friend $friend) => FriendData::fromFriend(
                $friend,
                $groups
                    ->filter(fn (Group $group) => $group->members->contains('id', $friend->friend_user_id))
                    ->pluck('name')
                    ->values()
                    ->all(),
            ))
            ->sortBy([
                fn (FriendData $a, FriendData $b) => ($a->name === null) <=> ($b->name === null),
                fn (FriendData $a, FriendData $b) => strnatcasecmp($a->name ?? $a->email ?? '', $b->name ?? $b->email ?? ''),
            ])
            ->values()
            ->all();

        return new self(items: $items);
    }
}
