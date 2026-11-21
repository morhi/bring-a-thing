<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

class PostComment
{
    /**
     * Post a comment on the given roster, item, event or poll, on behalf of the given user.
     */
    public function handle(Roster|RosterItem|Event|Poll $commentable, User $user, string $body): Comment
    {
        $comment = $commentable->comments()->make(['body' => $body]);
        $comment->user_id = $user->getKey();
        $comment->save();

        return $comment;
    }
}
