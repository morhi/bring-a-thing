<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class DashboardData extends Data
{
    /**
     * @param  array<int, DashboardWaitingData>  $waiting
     * @param  array<int, DashboardEventData>  $upcoming
     * @param  array<int, DashboardClaimData>  $claims
     * @param  array<int, DashboardGroupData>  $groups
     * @param  array<int, DashboardListData>  $lists
     */
    public function __construct(
        public array $waiting,
        public array $upcoming,
        public array $claims,
        public array $groups,
        public array $lists,
        public DashboardCountsData $counts,
    ) {}

    /**
     * Build the dashboard of a person (F-22).
     *
     * Everything is scoped to the person's groups and visibility rules, and
     * each part loads its relations up front, so the query count does not
     * grow with the data. Past events and lists are left out.
     */
    public static function forUser(User $user): self
    {
        $groups = $user->groups()
            ->withCount([
                'members',
                'polls as open_polls_count' => fn (Builder $query) => $query->whereNull('closed_at')->visibleTo($user),
                'events as upcoming_events_count' => fn (Builder $query) => $query->upcoming(),
            ])
            ->orderBy('groups.name')
            ->get();
        $groupIds = $groups->modelKeys();

        $waiting = [
            ...self::pollsToAnswer($user, $groupIds)->map(fn (Poll $poll) => DashboardWaitingData::fromPoll($poll))->all(),
            ...self::eventsWith($user, $groupIds, ParticipationStatus::Pending)->map(fn (Event $event) => DashboardWaitingData::fromEvent($event))->all(),
        ];
        $upcoming = self::eventsWith($user, $groupIds, ParticipationStatus::Accepted)->map(fn (Event $event) => DashboardEventData::fromEvent($event))->all();
        $claims = self::upcomingClaims($user, $groupIds)->map(fn (RosterItemClaim $claim) => DashboardClaimData::fromClaim($claim))->all();
        $groupCards = $groups->map(fn ($group) => DashboardGroupData::fromGroup($group))->all();
        $lists = self::standaloneLists($user)->map(fn (Roster $roster) => DashboardListData::fromRoster($roster))->all();

        return new self(
            waiting: $waiting,
            upcoming: $upcoming,
            claims: $claims,
            groups: $groupCards,
            lists: $lists,
            counts: new DashboardCountsData(count($waiting), count($upcoming), count($claims), count($groupCards), count($lists)),
        );
    }

    /**
     * The open polls in the person's groups they may see and have not answered for every option (F-06, F-12).
     *
     * Follow-up polls pass through Poll::visibleTo(), so only accepted
     * participants and managers of the source event get them.
     *
     * @param  array<int, int>  $groupIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Poll>
     */
    private static function pollsToAnswer(User $user, array $groupIds)
    {
        return Poll::query()
            ->whereIn('group_id', $groupIds)
            ->whereNull('closed_at')
            ->visibleTo($user)
            ->with([
                'group',
                'event',
                'options.responses' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (Poll $poll) => $poll->options->isNotEmpty() && $poll->options->contains(fn ($option) => $option->responses->isEmpty()))
            ->values();
    }

    /**
     * The upcoming events of the person's groups where they have the given status (F-10, F-11).
     *
     * Loads the accepted participations, which fill the avatar stack and the accepted count.
     *
     * @param  array<int, int>  $groupIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Event>
     */
    private static function eventsWith(User $user, array $groupIds, ParticipationStatus $status)
    {
        return Event::query()
            ->upcoming()
            ->whereIn('group_id', $groupIds)
            ->whereHas('participations', fn (Builder $query) => $query
                ->where('user_id', $user->getKey())
                ->where('status', $status))
            ->with([
                'group',
                'participations' => fn ($query) => $query->where('status', ParticipationStatus::Accepted)->with('user:id,name')->orderBy('id'),
            ])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The person's claims on lists that are not past and that they still see (F-15, F-22).
     *
     * An event list needs the person's participation and an event that has
     * not started; a group list needs membership; a standalone list shows
     * through the claim itself. A list or item without a date stays.
     *
     * @param  array<int, int>  $groupIds
     * @return Collection<int, RosterItemClaim>
     */
    private static function upcomingClaims(User $user, array $groupIds)
    {
        $today = Carbon::today();

        return RosterItemClaim::query()
            ->where('user_id', $user->getKey())
            ->with([
                'rosterItem.roster.event.participations' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->get()
            ->filter(function (RosterItemClaim $claim) use ($user, $groupIds, $today) {
                $roster = $claim->rosterItem->roster;
                $date = $claim->rosterItem->date ?? $roster->date;

                if ($roster->event_id !== null) {
                    return $roster->event->hasParticipant($user) && ! $roster->event->isPast();
                }

                if ($roster->group_id !== null && ! in_array($roster->group_id, $groupIds, true)) {
                    return false;
                }

                return $date === null || $date->gte($today);
            })
            ->sortBy(fn (RosterItemClaim $claim) => ($claim->rosterItem->date ?? $claim->rosterItem->roster->date ?? $claim->rosterItem->roster->event?->date)?->format('Y-m-d') ?? '9999-12-31')
            ->values();
    }

    /**
     * The standalone lists the person owns or claims on that are not past (F-13, F-22).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Roster>
     */
    private static function standaloneLists(User $user)
    {
        return Roster::query()
            ->whereNull('group_id')
            ->whereNull('event_id')
            ->where(fn (Builder $query) => $query->whereNull('date')->orWhereDate('date', '>=', Carbon::today()))
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $user->getKey())
                ->orWhereHas('items.claims', fn (Builder $claim) => $claim->where('user_id', $user->getKey())))
            ->with('items.claims')
            ->orderByRaw('date is null')
            ->orderBy('date')
            ->latest('id')
            ->get();
    }
}
