<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Roster;
use App\Models\User;

class RosterPolicy
{
    /**
     * Determine whether the user can create a standalone roster.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create a roster attached to the given group.
     */
    public function createForGroup(User $user, Group $group): bool
    {
        return $group->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Determine whether the user can view the roster.
     */
    public function view(User $user, Roster $roster): bool
    {
        if ($roster->owner_id === $user->getKey()) {
            return true;
        }

        return $roster->group_id !== null && $roster->group->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Determine whether the user can update the roster.
     */
    public function update(User $user, Roster $roster): bool
    {
        if ($roster->owner_id === $user->getKey()) {
            return true;
        }

        return $roster->group_id !== null && $roster->group->isAtLeastAdmin($user);
    }

    /**
     * Determine whether the user can delete the roster.
     */
    public function delete(User $user, Roster $roster): bool
    {
        return $roster->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can duplicate the roster.
     */
    public function duplicate(User $user, Roster $roster): bool
    {
        return $roster->owner_id === $user->getKey();
    }
}
