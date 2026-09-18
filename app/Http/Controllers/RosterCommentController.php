<?php

namespace App\Http\Controllers;

use App\Actions\Comments\PostComment;
use App\Events\CommentDeleted;
use App\Events\CommentPosted;
use App\Http\Requests\Rosters\StoreRosterCommentRequest;
use App\Models\Comment;
use App\Models\Roster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RosterCommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Post a comment on the roster's list-level thread.
     */
    public function store(StoreRosterCommentRequest $request, Roster $roster, PostComment $postComment): RedirectResponse
    {
        $comment = $postComment->handle($roster, $request->user(), $request->validated('body'));

        CommentPosted::dispatch($comment, $roster->id);

        return back()->with('success', 'Comment posted.');
    }

    /**
     * Delete a comment from the roster's list-level thread.
     */
    public function destroy(Roster $roster, Comment $comment): RedirectResponse
    {
        if ($comment->commentable_type !== $roster->getMorphClass() || $comment->commentable_id !== $roster->id) {
            throw new NotFoundHttpException;
        }

        $this->authorize('delete', $comment);

        [$commentableType, $commentableId] = [$comment->commentable_type, $comment->commentable_id];
        $comment->delete();

        CommentDeleted::dispatch($roster->id, $comment->id, $commentableType, $commentableId);

        return back()->with('success', 'Comment removed.');
    }
}
