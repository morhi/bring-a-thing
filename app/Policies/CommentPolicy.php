<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can delete the comment.
     *
     * Granted to the comment's own author, and to whoever can manage the
     * roster it's on (owner, or group admin for a group-attached roster).
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->getKey()) {
            return true;
        }

        $roster = $comment->commentable instanceof RosterItem
            ? $comment->commentable->roster
            : $comment->commentable;

        return $roster instanceof Roster && $user->can('update', $roster);
    }
}
