<?php

namespace App\Models;

use Database\Factories\PollWithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person's withdrawal from a poll they were invited to by accident (F-08).
 *
 * @property int $id
 * @property int $poll_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id'])]
class PollWithdrawal extends Model
{
    /** @use HasFactory<PollWithdrawalFactory> */
    use HasFactory;

    /**
     * The poll the person withdrew from.
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * The person who withdrew.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
