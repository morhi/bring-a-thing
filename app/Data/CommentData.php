<?php

namespace App\Data;

use App\Models\Comment;
use App\Models\User;
use Spatie\LaravelData\Data;

class CommentData extends Data
{
    public function __construct(
        public int $id,
        public string $body,
        public int $user_id,
        public ?string $user_name,
        public string $created_at,
        public bool $can_delete,
    ) {}

    /**
     * Build one comment of a list or item thread for the viewing user (F-20).
     */
    public static function forUser(Comment $comment, User $user): self
    {
        return new self(
            id: $comment->id,
            body: $comment->body,
            user_id: $comment->user_id,
            user_name: $comment->user?->name,
            created_at: $comment->created_at?->toIso8601String() ?? '',
            can_delete: $user->can('delete', $comment),
        );
    }
}
