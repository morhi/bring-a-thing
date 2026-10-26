<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $slug
 * @property int $group_id
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'location', 'date', 'starts_at', 'end_date', 'ends_at'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
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
            'end_date' => 'date',
        ];
    }

    /**
     * The group this event belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The user who created the event directly or fixed it from a poll.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * The poll option this event was fixed from (F-09), if any.
     */
    public function originPollOption(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'origin_poll_option_id');
    }

    /**
     * The source event of a follow-up poll this event was fixed from (F-12), if any.
     */
    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_event_id');
    }

    /**
     * The events fixed from this event's follow-up polls (one level only).
     */
    public function childEvents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_event_id')->orderBy('date')->orderBy('starts_at');
    }

    /**
     * Every participation status recorded for this event.
     */
    public function participations(): HasMany
    {
        return $this->hasMany(EventParticipation::class);
    }

    /**
     * The lists attached to this event.
     */
    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class);
    }

    /**
     * The follow-up polls created within this event (F-12).
     */
    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
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
     * The group members who see and answer the event's follow-up polls (F-12).
     *
     * @return Collection<int, User>
     */
    public function followUpAudience(): Collection
    {
        $this->loadMissing('group.members', 'participations');

        return $this->group->members
            ->filter(fn (User $member) => $this->admitsToFollowUpPolls($member))
            ->toBase()
            ->values();
    }

    /**
     * Whether a member loaded through group.members sees the follow-up polls: accepted participants and managers (F-11, F-12).
     *
     * Reads the role from the membership pivot, so it costs no query per member.
     */
    public function admitsToFollowUpPolls(User $member): bool
    {
        if (in_array($member->getKey(), [$this->organizer_id, $this->group->owner_id], true)) {
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
     */
    public function isManagedBy(User $user): bool
    {
        if ($this->organizer_id === $user->getKey()) {
            return true;
        }

        return $this->group->isAtLeastAdmin($user);
    }
}
