<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

class PostComment
{
    /**
     * Post a comment on the given roster or item, on behalf of the given user.
     */
    public function handle(Roster|RosterItem|Event $commentable, User $user, string $body): Comment
    {
        $comment = $commentable->comments()->make(['body' => $body]);
        $comment->user_id = $user->getKey();
        $comment->save();

        return $comment;
    }
}
