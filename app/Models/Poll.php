<?php

namespace App\Models;

use App\Enums\PollGranularity;
use App\Enums\PollResponseStatus;
use App\Enums\PollType;
use App\Models\Concerns\HasSlug;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property PollType $type
 * @property PollGranularity $granularity
 * @property int $organizer_id
 * @property int $group_id
 * @property Carbon|null $closed_at
 * @property int|null $chosen_option_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title'])]
class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PollType::class,
            'granularity' => PollGranularity::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The user who created and organizes the poll.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * The group this poll is attached to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The candidate days/slots on this poll.
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('date')->orderBy('starts_at');
    }

    /**
     * The option chosen when this poll was closed, if any (date-finder polls only).
     */
    public function chosenOption(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'chosen_option_id');
    }

    /**
     * Whether the poll is closed to new votes.
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Close the poll to new votes.
     *
     * A date-finder poll requires an option from this poll to record as the
     * chosen result; an attendance poll has no single winner and simply
     * freezes, since it is designed to stay open indefinitely otherwise.
     */
    public function close(?PollOption $chosenOption = null): void
    {
        $this->closed_at = now();
        $this->chosen_option_id = $chosenOption?->getKey();
        $this->save();
    }

    /**
     * Reopen the poll to new votes, clearing any chosen option.
     */
    public function reopen(): void
    {
        $this->closed_at = null;
        $this->chosen_option_id = null;
        $this->save();
    }

    /**
     * The candidate option(s) with the most "yes" responses.
     *
     * Only meaningful for date-finder polls, which converge on a result;
     * attendance polls stay open and have no single "best" option. Ties are
     * returned together rather than picking one arbitrarily.
     *
     * @return Collection<int, PollOption>
     */
    public function bestOptions(): Collection
    {
        $options = $this->options()->with('responses')->get();

        $highestYesCount = $options->max(
            fn (PollOption $option) => $option->responses->where('status', PollResponseStatus::Yes)->count()
        );

        if ($highestYesCount === 0) {
            return new Collection;
        }

        return $options->filter(
            fn (PollOption $option) => $option->responses->where('status', PollResponseStatus::Yes)->count() === $highestYesCount
        )->values();
    }
}
