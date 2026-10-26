<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['body'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * The roster, roster item or event this comment is on (exactly one, F-20).
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The private channel whose viewers see this comment's thread live (F-21).
     *
     * Item comments go to their list's channel, because the item thread is
     * shown on the list page.
     */
    public function broadcastChannelName(): string
    {
        $commentable = $this->commentable;

        if ($commentable instanceof Event) {
            return 'event.'.$commentable->id;
        }

        if ($commentable instanceof RosterItem) {
            return 'roster.'.$commentable->roster_id;
        }

        return 'roster.'.$this->commentable_id;
    }

    /**
     * The member who posted the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
