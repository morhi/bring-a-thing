<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollGranularity;
use App\Enums\PollResponseStatus;
use App\Enums\PollType;
use App\Models\Concerns\HasSlug;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
 * @property int|null $event_id
 * @property Carbon|null $closed_at
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
     * The source event of a follow-up poll (F-12), if any.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The candidate days or slots, in calendar order.
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('date')->orderBy('starts_at')->orderBy('id');
    }

    /**
     * The events fixed from this poll's options (F-09).
     */
    public function fixedEvents(): HasManyThrough
    {
        return $this->hasManyThrough(Event::class, PollOption::class, 'poll_id', 'origin_poll_option_id')
            ->orderBy('events.date')
            ->orderBy('events.starts_at');
    }

    /**
     * Whether this is a date finder poll, the only type with a best option and fixing (F-07).
     */
    public function isDateFinder(): bool
    {
        return $this->type === PollType::DateFinder;
    }

    /**
     * Whether the poll is closed to new responses and option changes (F-08).
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Close the poll to new responses and option changes.
     */
    public function close(): void
    {
        $this->closed_at = now();
        $this->save();
    }

    /**
     * Reopen the poll; events fixed from it stay (F-08).
     */
    public function reopen(): void
    {
        $this->closed_at = null;
        $this->save();
    }

    /**
     * Whether the user may see and answer the poll (F-06, F-12).
     */
    public function isOpenTo(User $user): bool
    {
        return $this->group->hasMember($user) && $this->includesMember($user);
    }

    /**
     * Whether a group member belongs to the poll's audience.
     *
     * Every member does, except on a follow-up poll (F-12): there only
     * accepted participants of the source event, the poll's organizer and
     * the event's managers (its organizer, group owner and admins) do.
     */
    public function includesMember(User $user): bool
    {
        if ($this->event_id === null || $this->organizer_id === $user->getKey()) {
            return true;
        }

        return $this->event->hasAccepted($user) || $this->event->isManagedBy($user);
    }

    /**
     * Limit the query to polls the user may see within their groups (F-12).
     *
     * Mirrors includesMember() for lists such as the group page: plain polls
     * for everyone, follow-up polls only for the poll's organizer, the
     * accepted participants and managers of the source event.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('event_id')
            ->orWhere('organizer_id', $user->getKey())
            ->orWhereHas('event', fn (Builder $event) => $event
                ->where('organizer_id', $user->getKey())
                ->orWhereHas('participations', fn (Builder $participation) => $participation
                    ->where('user_id', $user->getKey())
                    ->where('status', ParticipationStatus::Accepted)))
            ->orWhereHas('group', fn (Builder $group) => $group
                ->where('owner_id', $user->getKey())
                ->orWhereHas('members', fn (Builder $member) => $member
                    ->whereKey($user->getKey())
                    ->whereIn('group_user.role', [GroupRole::Owner, GroupRole::Admin]))));
    }

    /**
     * The group members who currently belong to the poll's audience (F-06, F-12).
     *
     * Works on the loaded members and participations, so it costs no query
     * per member; includesMember() is the single-user counterpart.
     *
     * @return Collection<int, User>
     */
    public function audience(): Collection
    {
        $this->loadMissing('group.members', 'event.participations');

        if ($this->event_id === null) {
            return $this->group->members->toBase()->values();
        }

        return $this->group->members
            ->filter(fn (User $member) => $member->getKey() === $this->organizer_id || $this->event->admitsToFollowUpPolls($member))
            ->toBase()
            ->values();
    }

    /**
     * Whether a person's responses count in totals, best option and fixing (F-12).
     *
     * On a plain poll every response counts, including those of former
     * members (F-04). On a follow-up poll only the current audience counts,
     * so a later decline removes the person's answers from the result
     * without deleting them.
     */
    public function countsResponseFrom(int $userId): bool
    {
        if ($this->event_id === null) {
            return true;
        }

        return $this->audience()->contains(fn (User $member) => $member->getKey() === $userId);
    }

    /**
     * Whether the poll may close without a new selection (F-08).
     *
     * An attendance poll closes without a selection. A date finder poll
     * closes by fixing dates, unless it already has a fixed option, as
     * after reopening a fixed poll.
     */
    public function canCloseWithoutFixing(): bool
    {
        if (! $this->isDateFinder()) {
            return true;
        }

        return $this->fixedEvents()->exists();
    }

    /**
     * The options that carry "Best option" (F-06).
     *
     * Most yes answers win; on a tie in yes answers only the options with the
     * fewest no answers keep the label, and options still tied all carry it.
     * Attendance polls and polls without any yes answer have no best option.
     *
     * @return Collection<int, PollOption>
     */
    public function bestOptions(): Collection
    {
        $mostYes = $this->optionsWithMostYes();

        if ($mostYes->isEmpty()) {
            return new Collection;
        }

        $fewestNo = $mostYes->min(fn (PollOption $option) => $option->countOf(PollResponseStatus::No));

        return $mostYes->filter(fn (PollOption $option) => $option->countOf(PollResponseStatus::No) === $fewestNo)->values();
    }

    /**
     * Whether no answers decided between options tied on yes, so the grid shows no-counts (F-06).
     */
    public function bestIsDecidedByNoAnswers(): bool
    {
        return $this->bestOptions()->count() < $this->optionsWithMostYes()->count();
    }

    /**
     * The options tied on the highest yes count, empty when nobody answered yes or the poll is an attendance poll.
     *
     * @return Collection<int, PollOption>
     */
    private function optionsWithMostYes(): Collection
    {
        if (! $this->isDateFinder()) {
            return new Collection;
        }

        $this->loadMissing('options.responses');
        $this->options->each(fn (PollOption $option) => $option->setRelation('poll', $this));

        $highestYesCount = $this->options->max(fn (PollOption $option) => $option->countOf(PollResponseStatus::Yes));

        if (! $highestYesCount) {
            return new Collection;
        }

        return $this->options
            ->filter(fn (PollOption $option) => $option->countOf(PollResponseStatus::Yes) === $highestYesCount)
            ->values()
            ->toBase();
    }
}
