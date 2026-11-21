<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollGranularity;
use App\Enums\PollResponseStatus;
use App\Enums\PollType;
use App\Models\Concerns\HasEntityInviteLink;
use App\Models\Concerns\HasSlug;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property PollType $type
 * @property PollGranularity $granularity
 * @property int $organizer_id
 * @property int|null $group_id
 * @property int|null $event_id
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $answer_deadline
 * @property bool $auto_close
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
            'answer_deadline' => 'datetime',
            'auto_close' => 'boolean',
        ];
    }

    /**
     * The user who created and organizes the poll.
     *
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * The group this poll is attached to; null for a standalone poll.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Remove the poll's comments with it: they are polymorphic and have no foreign key (F-20).
     */
    protected static function booted(): void
    {
        static::deleting(function (Poll $poll): void {
            $poll->comments()->delete();
        });
    }

    /**
     * The poll's own comment thread, oldest first (F-20).
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }

    /**
     * The source event of a follow-up poll (F-12), if any.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The candidate days or slots, in calendar order.
     *
     * @return HasMany<PollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderByRaw('coalesce(poll_options.starts_at, poll_options.date)')->orderBy('id');
    }

    /**
     * The events fixed from this poll's options (F-09).
     *
     * @return HasManyThrough<Event, PollOption, $this>
     */
    public function fixedEvents(): HasManyThrough
    {
        return $this->hasManyThrough(Event::class, PollOption::class, 'poll_id', 'origin_poll_option_id')
            ->chronological();
    }

    /**
     * The people who withdrew from the poll; they leave its audience (F-08).
     *
     * @return HasMany<PollWithdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(PollWithdrawal::class);
    }

    /**
     * Whether the user withdrew from the poll (F-08).
     */
    public function hasWithdrawn(User $user): bool
    {
        if ($this->relationLoaded('withdrawals')) {
            return $this->withdrawals->contains('user_id', $user->getKey());
        }

        return $this->withdrawals()->where('user_id', $user->getKey())->exists();
    }

    /**
     * Whether an event was fixed from one of the poll's options (F-09).
     */
    public function hasFixedOption(): bool
    {
        return $this->fixedEvents()->exists();
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
     *
     * Past its deadline, auto-close turns off, or the scheduler would close the poll again right away.
     */
    public function reopen(): void
    {
        $this->closed_at = null;

        if ($this->hasPassedDeadline()) {
            $this->auto_close = false;
        }

        $this->save();
    }

    /**
     * Set the optional answer deadline; auto-close needs a deadline to act on (F-08).
     */
    public function setAnswerDeadline(?CarbonInterface $deadline, bool $autoClose): void
    {
        $this->answer_deadline = $deadline?->copy()->utc();
        $this->auto_close = $deadline !== null && $autoClose;
    }

    /**
     * Whether the poll has an answer deadline that lies in the past (F-08).
     */
    public function hasPassedDeadline(): bool
    {
        return $this->answer_deadline !== null && $this->answer_deadline->lessThanOrEqualTo(now());
    }

    /**
     * Whether the poll was closed by its answer deadline rather than by hand or by fixing (F-08).
     */
    public function wasClosedAutomatically(): bool
    {
        return $this->isClosed() && $this->auto_close && $this->hasPassedDeadline();
    }

    /**
     * Whether dates may still be fixed: on an open poll, and on one auto-closed before anything was fixed (F-08, F-09).
     */
    public function allowsFixing(): bool
    {
        if (! $this->isClosed()) {
            return true;
        }

        return $this->wasClosedAutomatically() && ! $this->hasFixedOption();
    }

    /**
     * Limit the query to open polls whose auto-close deadline has passed (F-08).
     *
     * @param  Builder<Poll>  $query
     */
    #[Scope]
    protected function dueForAutoClose(Builder $query): void
    {
        $query->whereNull('closed_at')
            ->where('auto_close', true)
            ->whereNotNull('answer_deadline')
            ->where('answer_deadline', '<=', now());
    }

    /**
     * Whether the user may see and answer the poll (F-06, F-12, F-26, F-27).
     *
     * Besides the audience within the group, or the organizer's circle of a
     * standalone poll (F-29), a grant on the poll itself
     * opens it. A grant reaching a follow-up poll through its source event
     * follows the follow-up rule instead: only with "accepted". A person
     * who withdrew loses the poll altogether (F-08).
     */
    public function isOpenTo(User $user): bool
    {
        if ($this->hasWithdrawn($user)) {
            return false;
        }

        $isInsider = $this->group_id === null || $this->group->hasMember($user);

        if ($isInsider && $this->includesMember($user)) {
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
     * The grant holders outside the group, or beside the organizer of a standalone poll, who belong to the poll's audience (F-26, F-27).
     *
     * Holders of a grant on the poll itself always do; holders reaching a
     * follow-up poll through its source event only with "accepted".
     * Holders who withdrew are left out (F-08).
     *
     * @return EloquentCollection<int, User>
     */
    public function guests(): EloquentCollection
    {
        if ($this->resolvedGuests !== null) {
            return $this->resolvedGuests;
        }

        $this->loadMissing('withdrawals');
        $insiders = $this->insiders();

        $direct = $this->holdersOf([['poll', $this->getKey()]]);
        $throughEvent = $this->event_id === null
            ? new EloquentCollection
            : $this->event->guests()->filter(fn (User $guest) => $this->event->admitsToFollowUpPolls($guest));

        return $this->resolvedGuests = $direct
            ->concat($throughEvent)
            ->unique('id')
            ->reject(fn (User $guest) => $insiders->contains('id', $guest->getKey()))
            ->reject(fn (User $guest) => $this->hasWithdrawn($guest))
            ->values();
    }

    /**
     * The people inside the poll's circle without a grant: the group members, or for a standalone poll its organizer and the source event's organizer (F-29).
     *
     * @return EloquentCollection<int, User>
     */
    public function insiders(): EloquentCollection
    {
        if ($this->group_id !== null) {
            $this->loadMissing('group.members');

            return $this->group->members;
        }

        $this->loadMissing('organizer', 'event.organizer');

        return new EloquentCollection(array_filter([$this->organizer, $this->event?->organizer]))->unique('id')->values();
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
     * no access to their follow-up polls. A member who withdrew from the
     * poll drops out of it (F-08). A standalone poll has no members: only
     * its organizer belongs to it this way, and on a follow-up poll also
     * the accepted participants and the managers of the source event (F-29).
     */
    public function includesMember(User $user): bool
    {
        if ($this->hasWithdrawn($user)) {
            return false;
        }

        if ($this->group_id === null && $this->event_id === null) {
            return $this->organizer_id === $user->getKey();
        }

        if ($this->event_id === null) {
            return true;
        }

        if ($this->group_id !== null && ! $this->group->hasMember($user)) {
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
     * A standalone poll shows to its organizer and, as a follow-up poll, to
     * the source event's organizer (F-29). Polls the user withdrew from are
     * left out (F-08).
     *
     * @param  Builder<Poll>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->notWithdrawnBy($user)->where(fn (Builder $query) => $query
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
            ->orWhere(fn (Builder $query) => $query->standaloneOrganizedBy($user))
            ->orWhere(fn (Builder $query) => $query->reachableThroughGrants($user)));
    }

    /**
     * Limit the query to polls the user reaches through a grant, mirroring isOpenTo() (F-26, F-27).
     *
     * A grant on the poll opens it; one reaching a follow-up poll through
     * its source event only with "accepted". Polls the user withdrew from
     * are left out (F-08).
     *
     * @param  Builder<Poll>  $query
     */
    #[Scope]
    protected function reachableThroughGrants(Builder $query, User $user): void
    {
        $query->notWithdrawnBy($user)->where(fn (Builder $query) => $query
            ->whereIn('polls.id', AccessGrant::idsFor($user, 'poll'))
            ->orWhereHas('event', fn (Builder $event) => $event
                ->reachableThroughGrants($user)
                ->whereHas('participations', fn (Builder $participation) => $participation
                    ->where('user_id', $user->getKey())
                    ->where('status', ParticipationStatus::Accepted))));
    }

    /**
     * Limit the query to standalone polls the user organizes, directly or through the source event (F-29).
     *
     * @param  Builder<Poll>  $query
     */
    #[Scope]
    protected function standaloneOrganizedBy(Builder $query, User $user): void
    {
        $query->whereNull('polls.group_id')->where(fn (Builder $query) => $query
            ->where('polls.organizer_id', $user->getKey())
            ->orWhereHas('event', fn (Builder $event) => $event->where('organizer_id', $user->getKey())));
    }

    /**
     * Limit the query to polls the user has not withdrawn from (F-08).
     *
     * @param  Builder<Poll>  $query
     */
    #[Scope]
    protected function notWithdrawnBy(Builder $query, User $user): void
    {
        $query->whereDoesntHave('withdrawals', fn (Builder $withdrawal) => $withdrawal->where('user_id', $user->getKey()));
    }

    /**
     * The people who currently belong to the poll's audience: group members, or the organizer of a standalone poll, and guests with a grant (F-06, F-12, F-26, F-27, F-29).
     *
     * Works on the loaded members, participations and withdrawals, so it
     * costs no query per member; includesMember() is the single-user
     * counterpart. Members who withdrew are left out (F-08).
     *
     * @return Collection<int, User>
     */
    public function audience(): Collection
    {
        $this->loadMissing('event.participations', 'withdrawals');

        $members = $this->insiders()->reject(fn (User $member) => $this->hasWithdrawn($member));

        if ($this->event_id === null) {
            return $members->toBase()->concat($this->guests())->values();
        }

        return $members
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
