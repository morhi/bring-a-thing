<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Group;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
     * Determine whether the user can create a list attached to the given event (F-13).
     *
     * Participants and whoever manages the event; section 8 allows lists
     * on an event that nobody has accepted yet.
     */
    public function createForEvent(User $user, Event $event): bool
    {
        if ($event->isManagedBy($user)) {
            return true;
        }

        return $event->hasParticipant($user);
    }

    /**
     * Determine whether the user can view the roster (F-13).
     *
     * Without access the list shows "Not found", so its existence stays hidden (section 8).
     */
    public function view(User $user, Roster $roster): Response
    {
        if ($roster->isVisibleTo($user)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the roster: list owner, group owner and admins (F-13).
     */
    public function update(User $user, Roster $roster): bool
    {
        return $roster->isManagedBy($user);
    }

    /**
     * Determine whether the user can delete the roster: list owner, group owner and admins (F-13).
     */
    public function delete(User $user, Roster $roster): bool
    {
        return $roster->isManagedBy($user);
    }

    /**
     * Determine whether the user can duplicate the roster.
     */
    public function duplicate(User $user, Roster $roster): bool
    {
        return $roster->owner_id === $user->getKey();
    }
}
