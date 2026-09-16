<?php

namespace App\Models;

use App\Enums\PollResponseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $poll_option_id
 * @property int $user_id
 * @property PollResponseStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'status'])]
class PollResponse extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PollResponseStatus::class,
        ];
    }

    /**
     * The option this response is for.
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    /**
     * The member who submitted this response.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
