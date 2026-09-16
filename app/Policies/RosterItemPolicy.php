<?php

namespace App\Policies;

use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

class RosterItemPolicy
{
    /**
     * Determine whether the user can add an item to the given roster.
     */
    public function create(User $user, Roster $roster): bool
    {
        return $roster->canBeAddedToBy($user);
    }

    /**
     * Determine whether the user can update the item.
     */
    public function update(User $user, RosterItem $item): bool
    {
        return $item->roster->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can delete the item.
     */
    public function delete(User $user, RosterItem $item): bool
    {
        return $item->roster->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can claim (or update their own claim on) the item.
     */
    public function claim(User $user, RosterItem $item): bool
    {
        return $user->can('view', $item->roster);
    }

    /**
     * Determine whether the user may claim or unclaim the item on behalf of other members.
     *
     * Granted to the roster owner and, for group-attached rosters, group admins.
     */
    public function manageClaims(User $user, RosterItem $item): bool
    {
        return $user->can('update', $item->roster);
    }

    /**
     * Determine whether the user may claim or unclaim the item specifically for the given target member.
     */
    public function claimFor(User $user, RosterItem $item, User $target): bool
    {
        return $this->manageClaims($user, $item) && $target->can('view', $item->roster);
    }
}
