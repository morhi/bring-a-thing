<?php

namespace App\Http\Controllers;

use App\Actions\Comments\PostComment;
use App\Events\CommentAdded;
use App\Events\CommentDeleted;
use App\Http\Requests\Events\StoreEventCommentRequest;
use App\Models\Comment;
use App\Models\Event;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventCommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Post a comment on the event's own thread (F-20).
     */
    public function store(StoreEventCommentRequest $request, Event $event, PostComment $postComment): RedirectResponse
    {
        $comment = $postComment->handle($event, $request->user(), $request->validated('body'));

        CommentAdded::dispatch($comment);

        return back()->with('success', 'Comment posted.');
    }

    /**
     * Delete a comment from the event's thread: author, group owner and admins only (F-20).
     */
    public function destroy(Event $event, Comment $comment): RedirectResponse
    {
        if ($comment->commentable_type !== $event->getMorphClass() || $comment->commentable_id !== $event->id) {
            throw new NotFoundHttpException;
        }

        $this->authorize('view', $event);
        $this->authorize('delete', $comment);

        $comment->delete();

        CommentDeleted::dispatch($comment);

        return back()->with('success', 'Comment removed.');
    }
}
