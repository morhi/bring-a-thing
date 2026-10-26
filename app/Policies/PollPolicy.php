<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PollPolicy
{
    /**
     * Determine whether the user can create a poll attached to the given group.
     */
    public function createForGroup(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    /**
     * Determine whether the user can view the poll.
     *
     * Everyone else sees "Not found" (section 8), so the poll's existence
     * stays hidden; on a follow-up poll that includes group members who have
     * not accepted the source event (F-12).
     */
    public function view(User $user, Poll $poll): Response
    {
        if ($poll->isOpenTo($user)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can answer the poll; a closed poll is rejected by the request with its message (F-08).
     */
    public function respond(User $user, Poll $poll): Response
    {
        return $this->view($user, $poll);
    }

    /**
     * Determine whether the user can edit the poll: organizer, group owner and admins (F-08).
     */
    public function update(User $user, Poll $poll): bool
    {
        if ($poll->organizer_id === $user->getKey() && $poll->group->hasMember($user)) {
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

    /**
     * Determine whether the user can fix dates: editors of a date finder poll only (F-07, F-09).
     */
    public function fix(User $user, Poll $poll): bool
    {
        return $poll->isDateFinder() && $this->update($user, $poll);
    }
}
