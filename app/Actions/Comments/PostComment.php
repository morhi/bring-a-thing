<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PostComment
{
    /**
     * Post a comment on the given roster or item, on behalf of the given user.
     */
    public function handle(Model $commentable, User $user, string $body): Comment
    {
        $comment = $commentable->comments()->make(['body' => $body]);
        $comment->user_id = $user->getKey();
        $comment->save();

        return $comment;
    }
}
