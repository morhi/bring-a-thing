<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
     * Determine whether the user can open the group settings.
     *
     * Owners and admins manage the invite link there; anyone else sees
     * "Not found" (section 8), so the page's existence stays hidden.
     */
    public function manageSettings(User $user, Group $group): Response
    {
        if ($group->isAtLeastAdmin($user)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can rename the group (F-03: owner only).
     */
    public function update(User $user, Group $group): bool
    {
        return $group->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can switch the option "Members can set the participation status for others".
     *
     * F-03: only the owner switches it; admins cannot.
     */
    public function switchOption(User $user, Group $group): bool
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
     * Determine whether the user can create, lock, revoke or regenerate the group's invite link (F-04).
     */
    public function manageInviteLink(User $user, Group $group): bool
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
