<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can create an event directly in the given group (F-10).
     */
    public function createForGroup(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    /**
     * Determine whether the user can view the event.
     *
     * Anyone outside the group sees "Not found" (section 8), so the event's existence stays hidden.
     */
    public function view(User $user, Event $event): Response
    {
        if ($event->group->hasMember($user)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can edit the event: organizer, group owner and admins (F-10).
     */
    public function update(User $user, Event $event): bool
    {
        return $event->isManagedBy($user);
    }

    /**
     * Determine whether the user can delete the event together with its lists, follow-up polls and child events.
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->isManagedBy($user);
    }

    /**
     * Determine whether the user can accept or decline the event for themselves (F-11).
     */
    public function respond(User $user, Event $event): Response
    {
        if (! $event->group->hasMember($user)) {
            return Response::denyAsNotFound();
        }

        if ($event->isPast()) {
            return Response::deny('This event has already started.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can set the participation status for other members (F-11).
     *
     * Organizer, owner and admins always can; plain members only while the
     * group option "Members can set the participation status for others" is on (F-03).
     */
    public function setStatusForOthers(User $user, Event $event): bool
    {
        if ($event->isPast()) {
            return false;
        }

        if ($event->isManagedBy($user)) {
            return true;
        }

        return $event->group->members_can_set_status && $event->group->hasMember($user);
    }

    /**
     * Determine whether the user can remove participants: organizer, owner and admins only (F-11).
     */
    public function removeParticipant(User $user, Event $event): bool
    {
        return $event->isManagedBy($user);
    }
}
