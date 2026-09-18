<?php

namespace App\Http\Controllers;

use App\Actions\Comments\PostComment;
use App\Events\CommentDeleted;
use App\Events\CommentPosted;
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
        $comment = $postComment->handle($item, $request->user(), $request->validated('body'));

        CommentPosted::dispatch($comment, $roster->id);

        return back()->with('success', 'Comment posted.');
    }

    /**
     * Delete a comment from the item's own thread.
     */
    public function destroy(Roster $roster, RosterItem $item, Comment $comment): RedirectResponse
    {
        if ($comment->commentable_type !== $item->getMorphClass() || $comment->commentable_id !== $item->id) {
            throw new NotFoundHttpException;
        }

        $this->authorize('delete', $comment);

        [$commentableType, $commentableId] = [$comment->commentable_type, $comment->commentable_id];
        $comment->delete();

        CommentDeleted::dispatch($roster->id, $comment->id, $commentableType, $commentableId);

        return back()->with('success', 'Comment removed.');
    }
}
