<?php

namespace App\Http\Controllers;

use App\Actions\Comments\PostComment;
use App\Events\CommentAdded;
use App\Events\CommentDeleted;
use App\Http\Requests\Polls\StorePollCommentRequest;
use App\Models\Comment;
use App\Models\Poll;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PollCommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Post a comment on the poll's own thread (F-20).
     */
    public function store(StorePollCommentRequest $request, Poll $poll, PostComment $postComment): RedirectResponse
    {
        $comment = $postComment->handle($poll, $request->user(), $request->validated('body'));

        CommentAdded::dispatch($comment);

        return back()->with('success', __('ui.poll.comments.posted'));
    }

    /**
     * Delete a comment from the poll's thread: author, group owner and admins only (F-20).
     */
    public function destroy(Poll $poll, Comment $comment): RedirectResponse
    {
        if ($comment->commentable_type !== $poll->getMorphClass() || $comment->commentable_id !== $poll->id) {
            throw new NotFoundHttpException;
        }

        $this->authorize('view', $poll);
        $this->authorize('delete', $comment);

        $comment->delete();

        CommentDeleted::dispatch($comment);

        return back()->with('success', __('ui.poll.comments.removed'));
    }
}
