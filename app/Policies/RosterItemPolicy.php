<?php

namespace App\Policies;

use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
     * Determine whether the user can claim (or update their own claim on) the item (F-15).
     *
     * On event lists only participants with "accepted" claim; a pending
     * participant gets the note to accept first.
     */
    public function claim(User $user, RosterItem $item): Response
    {
        if (! $item->roster->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        if (! $item->roster->allowsClaimsBy($user)) {
            return Response::deny('Accept the event first to claim items.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can remove their own claim on the item (F-15).
     *
     * Needs no acceptance: a moved event resets acceptances to "pending"
     * while claims stay, and the person must still be able to let go.
     */
    public function unclaim(User $user, RosterItem $item): bool
    {
        return $item->roster->isVisibleTo($user);
    }

    /**
     * Determine whether the user may claim or unclaim the item on behalf of other members.
     *
     * Granted to list owner, group owner and admins, and for event lists the event's organizer.
     */
    public function manageClaims(User $user, RosterItem $item): bool
    {
        return $user->can('update', $item->roster);
    }

    /**
     * Determine whether the user may claim the item specifically for the given target person (F-15).
     *
     * The target must see the list and, on event lists, have accepted.
     */
    public function claimFor(User $user, RosterItem $item, User $target): bool
    {
        if (! $this->manageClaims($user, $item)) {
            return false;
        }

        return $item->roster->isVisibleTo($target) && $item->roster->allowsClaimsBy($target);
    }

    /**
     * Determine whether the user may remove the given target person's claim on the item (F-15).
     */
    public function unclaimFor(User $user, RosterItem $item, User $target): bool
    {
        return $this->manageClaims($user, $item);
    }
}
