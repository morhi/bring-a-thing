<?php

namespace App\Actions\Groups;

use App\Enums\GroupJoinMethod;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;

class JoinGroupViaLink
{
    /**
     * Add the user to the group as an accepted member labeled "Joined via link" (F-04).
     *
     * Returns false when the user already belongs to the group, so an
     * existing role or label is never overwritten.
     */
    public function handle(Group $group, User $user): bool
    {
        if ($group->hasMember($user)) {
            return false;
        }

        $group->addMember($user, GroupRole::Member, now(), GroupJoinMethod::Link);

        return true;
    }
}
