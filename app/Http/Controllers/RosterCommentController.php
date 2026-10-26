<?php

namespace App\Http\Controllers;

use App\Actions\Comments\PostComment;
use App\Events\CommentAdded;
use App\Events\CommentDeleted;
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

        CommentAdded::dispatch($comment);

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

        $comment->delete();

        CommentDeleted::dispatch($comment);

        return back()->with('success', 'Comment removed.');
    }
}
