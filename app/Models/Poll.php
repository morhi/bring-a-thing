<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollGranularity;
use App\Enums\PollResponseStatus;
use App\Enums\PollType;
use App\Models\Concerns\HasEntityInviteLink;
use App\Models\Concerns\HasSlug;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
 * @property string|null $invite_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title'])]
#[Hidden(['invite_token'])]
class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasEntityInviteLink, HasFactory, HasSlug;

    /**
     * The guests resolved once per instance, since audience checks run per response.
     *
     * @var EloquentCollection<int, User>|null
     */
    protected ?EloquentCollection $resolvedGuests = null;

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
     * Whether the user may see and answer the poll (F-06, F-12, F-26, F-27).
     *
     * Besides the audience within the group, a grant on the poll itself
     * opens it. A grant reaching a follow-up poll through its source event
     * follows the follow-up rule instead: only with "accepted".
     */
    public function isOpenTo(User $user): bool
    {
        if ($this->group->hasMember($user) && $this->includesMember($user)) {
            return true;
        }

        if ($this->accessGrants()->where('user_id', $user->getKey())->exists()) {
            return true;
        }

        if ($this->event_id === null || ! $this->event->grantsAccessTo($user)) {
            return false;
        }

        return $this->event->hasAccepted($user);
    }

    /**
     * The grant sources that open this poll: its own link and, for a follow-up poll, those of its source event (F-26, F-27).
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public function grantSources(): array
    {
        $sources = [['poll', $this->getKey()]];

        if ($this->event_id !== null && $this->event !== null) {
            $sources = [...$sources, ...$this->event->grantSources()];
        }

        return $sources;
    }

    /**
     * The grant holders outside the group who belong to the poll's audience (F-26, F-27).
     *
     * Holders of a grant on the poll itself always do; holders reaching a
     * follow-up poll through its source event only with "accepted".
     *
     * @return EloquentCollection<int, User>
     */
    public function guests(): EloquentCollection
    {
        if ($this->resolvedGuests !== null) {
            return $this->resolvedGuests;
        }

        $this->loadMissing('group.members');

        $direct = User::query()
            ->whereHas('accessGrants', fn (Builder $grant) => $grant->where('accessible_type', 'poll')->where('accessible_id', $this->getKey()))
            ->orderBy('id')
            ->get();
        $throughEvent = $this->event_id === null
            ? new EloquentCollection
            : $this->event->guests()->filter(fn (User $guest) => $this->event->admitsToFollowUpPolls($guest));

        return $this->resolvedGuests = $direct
            ->concat($throughEvent)
            ->unique('id')
            ->reject(fn (User $guest) => $this->group->members->contains('id', $guest->getKey()))
            ->values();
    }

    /**
     * The name of the route that shows the poll's join page.
     */
    protected function inviteJoinRouteName(): string
    {
        return 'polls.join.show';
    }

    /**
     * Whether a group member belongs to the poll's audience.
     *
     * Every member does, except on a follow-up poll (F-12): there only
     * accepted participants of the source event, the poll's organizer and
     * the event's managers (its organizer, group owner and admins) do. The
     * organizer role lapses with the membership, so a member who left keeps
     * no access to their follow-up polls.
     */
    public function includesMember(User $user): bool
    {
        if ($this->event_id === null) {
            return true;
        }

        if (! $this->group->hasMember($user)) {
            return false;
        }

        if ($this->organizer_id === $user->getKey()) {
            return true;
        }

        return $this->event->hasAccepted($user) || $this->event->isManagedBy($user);
    }

    /**
     * Limit the query to polls the user may see within their groups (F-12).
     *
     * Mirrors includesMember() for lists such as the group page: plain polls
     * for everyone, follow-up polls only for the poll's organizer, the
     * accepted participants and managers of the source event. Every rule
     * requires current group membership, so organizer_id alone grants nothing.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query
                ->whereHas('group.members', fn (Builder $member) => $member->whereKey($user->getKey()))
                ->where(fn (Builder $query) => $query
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
                            ->whereIn('group_user.role', [GroupRole::Owner, GroupRole::Admin])))))
            ->orWhere(fn (Builder $query) => $query->reachableThroughGrants($user)));
    }

    /**
     * Limit the query to polls the user reaches through a grant, mirroring isOpenTo() (F-26, F-27).
     *
     * A grant on the poll opens it; one reaching a follow-up poll through
     * its source event only with "accepted".
     */
    #[Scope]
    protected function reachableThroughGrants(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('polls.id', AccessGrant::idsFor($user, 'poll'))
            ->orWhereHas('event', fn (Builder $event) => $event
                ->reachableThroughGrants($user)
                ->whereHas('participations', fn (Builder $participation) => $participation
                    ->where('user_id', $user->getKey())
                    ->where('status', ParticipationStatus::Accepted))));
    }

    /**
     * The people who currently belong to the poll's audience: group members and guests with a grant (F-06, F-12, F-26, F-27).
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
            return $this->group->members->toBase()->concat($this->guests())->values();
        }

        return $this->group->members
            ->filter(fn (User $member) => $member->getKey() === $this->organizer_id || $this->event->admitsToFollowUpPolls($member))
            ->toBase()
            ->concat($this->guests())
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
