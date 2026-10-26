<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CommentDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public int $commentId;

    public string $commentableType;

    public int $commentableId;

    public string $channelName;

    /**
     * Create a new event instance from the comment just deleted (F-20, F-21).
     *
     * Reads only the comment's own attributes and its still existing
     * commentable, so it works after the row is gone.
     */
    public function __construct(Comment $comment)
    {
        $this->commentId = $comment->id;
        $this->commentableType = $comment->commentable_type;
        $this->commentableId = $comment->commentable_id;
        $this->channelName = $comment->broadcastChannelName();
    }

    /**
     * Get the channel the event should broadcast on: the list's or the event's.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel($this->channelName);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'comment.deleted';
    }

    /**
     * Get the ids the client needs to drop the comment from its thread.
     *
     * @return array{comment_id: int, commentable_type: string, commentable_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'comment_id' => $this->commentId,
            'commentable_type' => $this->commentableType,
            'commentable_id' => $this->commentableId,
        ];
    }
}
