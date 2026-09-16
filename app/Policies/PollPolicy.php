<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Poll;
use App\Models\User;

class PollPolicy
{
    /**
     * Determine whether the user can create a poll attached to the given group.
     */
    public function createForGroup(User $user, Group $group): bool
    {
        return $group->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Determine whether the user can view the poll.
     */
    public function view(User $user, Poll $poll): bool
    {
        return $poll->group->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Determine whether the user can vote on the poll.
     */
    public function respond(User $user, Poll $poll): bool
    {
        return $this->view($user, $poll);
    }

    /**
     * Determine whether the user can update the poll's options.
     */
    public function update(User $user, Poll $poll): bool
    {
        if ($poll->organizer_id === $user->getKey()) {
            return true;
        }

        return $poll->group->isAtLeastAdmin($user);
    }

    /**
     * Determine whether the user can delete the poll.
     */
    public function delete(User $user, Poll $poll): bool
    {
        return $this->update($user, $poll);
    }
}
