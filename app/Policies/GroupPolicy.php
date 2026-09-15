<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Determine whether the user can create groups.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the group.
     */
    public function view(User $user, Group $group): bool
    {
        return $group->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Determine whether the user can update the group.
     */
    public function update(User $user, Group $group): bool
    {
        return $group->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can delete the group.
     */
    public function delete(User $user, Group $group): bool
    {
        return $group->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can invite members to the group.
     */
    public function invite(User $user, Group $group): bool
    {
        return $group->owner_id === $user->getKey();
    }
}
