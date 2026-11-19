<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use App\Models\Concerns\HasEntityInviteLink;
use App\Models\Concerns\HasSlug;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $slug
 * @property int|null $group_id
 * @property int $organizer_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $date
 * @property string|null $starts_at
 * @property Carbon|null $end_date
 * @property string|null $ends_at
 * @property int|null $origin_poll_option_id
 * @property int|null $parent_event_id
 * @property string|null $invite_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'location', 'date', 'starts_at', 'end_date', 'ends_at'])]
#[Hidden(['invite_token'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
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
            'date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The group this event belongs to; null for a standalone event.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The user who created the event directly or fixed it from a poll.
     *
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * The poll option this event was fixed from (F-09), if any.
     *
     * @return BelongsTo<PollOption, $this>
     */
    public function originPollOption(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'origin_poll_option_id');
    }

    /**
     * The source event of a follow-up poll this event was fixed from (F-12), if any.
     *
     * @return BelongsTo<Event, $this>
     */
    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_event_id');
    }

    /**
     * The events fixed from this event's follow-up polls (one level only).
     *
     * @return HasMany<Event, $this>
     */
    public function childEvents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_event_id')->orderBy('date')->orderBy('starts_at');
    }

    /**
     * Every participation status recorded for this event.
     *
     * @return HasMany<EventParticipation, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(EventParticipation::class);
    }

    /**
     * The lists attached to this event.
     *
     * @return HasMany<Roster, $this>
     */
    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class);
    }

    /**
     * The follow-up polls created within this event (F-12).
     *
     * @return HasMany<Poll, $this>
     */
    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    /**
     * The event's own comment thread, oldest first (F-20).
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }

    /**
     * The moment the event starts, at midnight when it has no time.
     */
    public function startsAt(): Carbon
    {
        if ($this->starts_at === null) {
            return $this->date->copy()->startOfDay();
        }

        return Carbon::parse($this->date->format('Y-m-d').' '.$this->starts_at);
    }

    /**
     * Whether the event's start has passed (F-10).
     *
     * An event without a time stays current for its whole day, so people
     * can still accept or decline on the day itself.
     */
    public function isPast(): bool
    {
        if ($this->starts_at === null) {
            return $this->date->copy()->endOfDay()->isPast();
        }

        return $this->startsAt()->isPast();
    }

    /**
     * Limit the query to events that have not started, mirroring isPast() (F-22).
     *
     * An event without a time stays current for its whole day.
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where(fn (Builder $query) => $query
            ->whereDate('date', '>', $today)
            ->orWhere(fn (Builder $query) => $query
                ->whereDate('date', $today)
                ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '>', now()->format('H:i:s')))));
    }

    /**
     * The given user's participation status, or null when they have none.
     */
    public function statusOf(User $user): ?ParticipationStatus
    {
        return $this->participations->firstWhere('user_id', $user->getKey())?->status;
    }

    /**
     * Whether the user takes part with "accepted" or "pending" and may therefore see the event's lists (F-11).
     */
    public function hasParticipant(User $user): bool
    {
        return in_array($this->statusOf($user), [ParticipationStatus::Accepted, ParticipationStatus::Pending], true);
    }

    /**
     * Whether the user takes part with "accepted" and may therefore see follow-up polls (F-11, F-12).
     */
    public function hasAccepted(User $user): bool
    {
        return $this->statusOf($user) === ParticipationStatus::Accepted;
    }

    /**
     * The people who see and answer the event's follow-up polls (F-12, F-26).
     *
     * Group members, or the organizer of a standalone event, and grant
     * holders follow the same rule: accepted participants and managers.
     *
     * @return Collection<int, User>
     */
    public function followUpAudience(): Collection
    {
        $this->loadMissing('participations');

        return $this->insiders()
            ->concat($this->guests())
            ->filter(fn (User $member) => $this->admitsToFollowUpPolls($member))
            ->toBase()
            ->values();
    }

    /**
     * The grant holders outside the group, or beside the organizer of a standalone event, who reach this event through an invite link (F-26, F-27).
     *
     * @return EloquentCollection<int, User>
     */
    public function guests(): EloquentCollection
    {
        if ($this->resolvedGuests !== null) {
            return $this->resolvedGuests;
        }

        $insiders = $this->insiders();

        return $this->resolvedGuests = $this->grantHolders()
            ->reject(fn (User $holder) => $insiders->contains('id', $holder->getKey()))
            ->values();
    }

    /**
     * The people inside the event's circle without a grant: the group members, or the organizer of a standalone event (F-29).
     *
     * @return EloquentCollection<int, User>
     */
    public function insiders(): EloquentCollection
    {
        if ($this->group_id !== null) {
            $this->loadMissing('group.members');

            return $this->group->members;
        }

        $this->loadMissing('organizer');

        return new EloquentCollection(array_filter([$this->organizer]));
    }

    /**
     * Whether the user is inside the event's circle without a grant: a group member, or the organizer of a standalone event (F-29).
     */
    public function hasInsider(User $user): bool
    {
        if ($this->group_id === null) {
            return $this->organizer_id === $user->getKey();
        }

        return $this->group->hasMember($user);
    }

    /**
     * Whether the user may open the event: a group member or a holder of a grant that covers it (F-10, F-26, F-27).
     *
     * A standalone event opens to its organizer, to its parent event's
     * organizer and to grant holders only (F-29).
     */
    public function admits(User $user): bool
    {
        if ($this->hasInsider($user)) {
            return true;
        }

        if ($this->group_id === null && $this->parent_event_id !== null && $this->parentEvent?->organizer_id === $user->getKey()) {
            return true;
        }

        return $this->grantsAccessTo($user);
    }

    /**
     * The grant sources that open this event: its own link, its parent event's and the poll it was fixed from (F-26, F-27).
     *
     * A parent event passes its sources on, so a grant on a poll also
     * covers the child events of the events fixed from it.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public function grantSources(): array
    {
        $sources = [['event', $this->getKey()]];

        if ($this->origin_poll_option_id !== null && $this->originPollOption !== null) {
            $sources[] = ['poll', $this->originPollOption->poll_id];
        }

        if ($this->parent_event_id !== null && $this->parentEvent !== null) {
            $sources = [...$sources, ...$this->parentEvent->grantSources()];
        }

        return $sources;
    }

    /**
     * Limit the query to events the user reaches through a grant, mirroring grantSources() (F-26, F-27).
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function reachableThroughGrants(Builder $query, User $user): void
    {
        $eventIds = AccessGrant::idsFor($user, 'event');
        $pollIds = AccessGrant::idsFor($user, 'poll');

        $query->where(fn (Builder $query) => $query
            ->whereIn('events.id', $eventIds)
            ->orWhereIn('events.parent_event_id', $eventIds)
            ->orWhereHas('originPollOption', fn (Builder $option) => $option->whereIn('poll_id', $pollIds))
            ->orWhereHas('parentEvent.originPollOption', fn (Builder $option) => $option->whereIn('poll_id', $pollIds)));
    }

    /**
     * Limit the query to standalone events the user organizes, directly or through the parent event (F-29).
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function standaloneOrganizedBy(Builder $query, User $user): void
    {
        $query->whereNull('events.group_id')->where(fn (Builder $query) => $query
            ->where('events.organizer_id', $user->getKey())
            ->orWhereHas('parentEvent', fn (Builder $parent) => $parent->where('organizer_id', $user->getKey())));
    }

    /**
     * Limit the query to events the user may open: their groups', their own standalone events and those reached through a grant (F-22, F-26, F-29).
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('events.group_id', $user->groups()->select('groups.id'))
            ->orWhere(fn (Builder $query) => $query->standaloneOrganizedBy($user))
            ->orWhere(fn (Builder $query) => $query->reachableThroughGrants($user)));
    }

    /**
     * The name of the route that shows the event's join page.
     */
    protected function inviteJoinRouteName(): string
    {
        return 'events.join.show';
    }

    /**
     * Whether a member loaded through group.members sees the follow-up polls: accepted participants and managers (F-11, F-12).
     *
     * Reads the role from the membership pivot, so it costs no query per member.
     */
    public function admitsToFollowUpPolls(User $member): bool
    {
        if (in_array($member->getKey(), [$this->organizer_id, $this->group?->owner_id], true)) {
            return true;
        }

        if ($member->pivot instanceof GroupMember && $member->pivot->isAtLeastAdmin()) {
            return true;
        }

        return $this->hasAccepted($member);
    }

    /**
     * The user's claims on items of this event's own lists, with item and list loaded.
     *
     * @return EloquentCollection<int, RosterItemClaim>
     */
    public function claimsOf(User $user): EloquentCollection
    {
        return RosterItemClaim::query()
            ->where('user_id', $user->getKey())
            ->whereHas('rosterItem.roster', fn (Builder $query) => $query->where('event_id', $this->getKey()))
            ->with('rosterItem.roster')
            ->get();
    }

    /**
     * Whether the user is the organizer or a group owner or admin, who edit and manage the event.
     *
     * A standalone event has only its organizer as manager (F-29).
     */
    public function isManagedBy(User $user): bool
    {
        if ($this->group_id === null) {
            return $this->organizer_id === $user->getKey();
        }

        // The organizer role lapses with the membership, so a removed member keeps no rights.
        if ($this->organizer_id === $user->getKey() && $this->group->hasMember($user)) {
            return true;
        }

        return $this->group->isAtLeastAdmin($user);
    }
}
