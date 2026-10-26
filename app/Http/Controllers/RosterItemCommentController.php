<?php

namespace App\Http\Controllers;

use App\Actions\Comments\PostComment;
use App\Events\CommentAdded;
use App\Events\CommentDeleted;
use App\Http\Requests\RosterItems\StoreRosterItemCommentRequest;
use App\Models\Comment;
use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RosterItemCommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Post a comment on the item's own thread.
     */
    public function store(StoreRosterItemCommentRequest $request, Roster $roster, RosterItem $item, PostComment $postComment): RedirectResponse
    {
        if ($item->roster_id !== $roster->id) {
            throw new NotFoundHttpException;
        }

        $comment = $postComment->handle($item, $request->user(), $request->validated('body'));

        CommentAdded::dispatch($comment);

        return back()->with('success', 'Comment posted.');
    }

    /**
     * Delete a comment from the item's own thread.
     */
    public function destroy(Roster $roster, RosterItem $item, Comment $comment): RedirectResponse
    {
        if ($item->roster_id !== $roster->id || $comment->commentable_type !== $item->getMorphClass() || $comment->commentable_id !== $item->id) {
            throw new NotFoundHttpException;
        }

        $this->authorize('delete', $comment);

        $comment->delete();

        CommentDeleted::dispatch($comment);

        return back()->with('success', 'Comment removed.');
    }
}
