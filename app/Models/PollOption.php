<?php

namespace App\Models;

use App\Enums\PollResponseStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\PollOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property int $poll_id
 * @property Carbon $date
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property string|null $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'starts_at', 'ends_at', 'label'])]
class PollOption extends Model
{
    /** @use HasFactory<PollOptionFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * The poll this option belongs to.
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * The members' responses to this option.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    /**
     * The event fixed from this option (F-09), if any.
     */
    public function event(): HasOne
    {
        return $this->hasOne(Event::class, 'origin_poll_option_id');
    }

    /**
     * Whether an event was fixed from this option, which locks it on the poll (F-09).
     */
    public function isFixed(): bool
    {
        if ($this->relationLoaded('event')) {
            return $this->event !== null;
        }

        return $this->event()->exists();
    }

    /**
     * Whether anyone answered this option (F-08).
     */
    public function hasAnswers(): bool
    {
        if ($this->relationLoaded('responses')) {
            return $this->responses->isNotEmpty();
        }

        return $this->responses()->exists();
    }

    /**
     * Whether date and time are locked: the option has answers or is fixed (F-08, F-09).
     *
     * Removing the option is then the only way to change them, and a fixed option cannot be removed.
     */
    public function hasLockedSchedule(): bool
    {
        return $this->hasAnswers() || $this->isFixed();
    }

    /**
     * Count the loaded responses with the given answer that still count (F-12).
     */
    public function countOf(PollResponseStatus $status): int
    {
        return $this->responses
            ->where('status', $status)
            ->filter(fn (PollResponse $response) => $this->poll->countsResponseFrom($response->user_id))
            ->count();
    }
}
