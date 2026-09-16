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
        return $group->isAtLeastAdmin($user);
    }

    /**
     * Determine whether the user can remove the given member from the group.
     *
     * The owner cannot be removed; a group always has exactly one owner in v1.
     */
    public function removeMember(User $user, Group $group, User $member): bool
    {
        return $group->isAtLeastAdmin($user) && $member->getKey() !== $group->owner_id;
    }

    /**
     * Determine whether the user can change the given member's admin status.
     *
     * Owner-only: admins cannot promote or demote other admins, and the
     * owner's own role can never be changed this way.
     */
    public function updateMemberRole(User $user, Group $group, User $member): bool
    {
        return $group->owner_id === $user->getKey() && $member->getKey() !== $group->owner_id;
    }
}
