<?php

namespace App\Models;

use App\Enums\PollResponseStatus;
use App\Models\Concerns\HasSlug;
use App\Support\WallClock;
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
 * @property Carbon|null $date
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Map a form row with a local date and optional times to the stored columns (section 9).
     *
     * A by-day option keeps its plain date; a time slot becomes two UTC
     * instants on the entered day in the given zone.
     *
     * @param  array{date: string, starts_at?: string|null, ends_at?: string|null}  $row
     * @return array{date: string|null, starts_at: Carbon|null, ends_at: Carbon|null}
     */
    public static function scheduleFrom(array $row, string $zone): array
    {
        $day = Carbon::parse($row['date'])->format('Y-m-d');
        [$date, $startsAt] = WallClock::toStored($day, $row['starts_at'] ?? null, $zone);
        [, $endsAt] = WallClock::toStored($day, $row['ends_at'] ?? null, $zone);

        return ['date' => $date, 'starts_at' => $startsAt, 'ends_at' => $endsAt];
    }

    /**
     * The poll this option belongs to.
     *
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * The members' responses to this option.
     *
     * @return HasMany<PollResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    /**
     * The event fixed from this option (F-09), if any.
     *
     * @return HasOne<Event, $this>
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
