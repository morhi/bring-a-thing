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
}
