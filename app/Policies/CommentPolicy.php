<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can delete the comment (F-20).
     *
     * Author, group owner and admins always can. On lists and items the
     * list owner can too; on events and polls nobody else, not even the organizer; a standalone
     * poll has no group, so only the author can.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->getKey()) {
            return true;
        }

        $commentable = $comment->commentable;

        if ($commentable instanceof Event || $commentable instanceof Poll) {
            return $commentable->group?->isAtLeastAdmin($user) ?? false;
        }

        $roster = $commentable instanceof RosterItem ? $commentable->roster : $commentable;

        if (! $roster instanceof Roster) {
            return false;
        }

        if ($roster->owner_id === $user->getKey()) {
            return true;
        }

        return $roster->managingGroup()?->isAtLeastAdmin($user) ?? false;
    }
}
