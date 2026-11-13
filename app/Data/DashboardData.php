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
     * @param  array<int, DashboardPollData>  $polls
     * @param  array<int, DashboardListData>  $lists
     * @param  array<int, DashboardGroupData>  $groups
     */
    public function __construct(
        public array $waiting,
        public array $upcoming,
        public array $claims,
        public array $polls,
        public array $lists,
        public array $groups,
        public ?DashboardWithoutGroupData $withoutGroup,
        public DashboardCountsData $counts,
    ) {}

    /**
     * Build the dashboard of a person (F-22).
     *
     * Everything is scoped to the person's groups and visibility rules, and
     * each part loads its relations up front, so the query count does not
     * grow with the data. Past events and lists are left out. The virtual
     * group "Without groups" only shows when it has content (F-29).
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

        $openPolls = self::openPolls($user);
        $waiting = [
            ...self::pollsToAnswer($openPolls)->map(fn (Poll $poll) => DashboardWaitingData::fromPoll($poll))->all(),
            ...self::eventsWith($user, $groupIds, ParticipationStatus::Pending)->map(fn (Event $event) => DashboardWaitingData::fromEvent($event))->all(),
        ];
        $upcoming = self::eventsWith($user, $groupIds, ParticipationStatus::Accepted)->map(fn (Event $event) => DashboardEventData::fromEvent($event))->all();
        $claims = self::upcomingClaims($user, $groupIds)->map(fn (RosterItemClaim $claim) => DashboardClaimData::fromClaim($claim))->all();
        // Follow-up polls get no row of their own; they live on their event page.
        $polls = $openPolls->whereNull('event_id')->map(fn (Poll $poll) => DashboardPollData::fromPoll($poll))->values()->all();
        $lists = self::activeLists($user)->map(fn (Roster $roster) => DashboardListData::fromRoster($roster))->all();
        $groupCards = $groups->map(fn ($group) => DashboardGroupData::fromGroup($group))->all();

        return new self(
            waiting: $waiting,
            upcoming: $upcoming,
            claims: $claims,
            polls: $polls,
            lists: $lists,
            groups: $groupCards,
            withoutGroup: WithoutGroupData::countsFor($user),
            counts: new DashboardCountsData(count($waiting), count($upcoming), count($claims), count($polls), count($lists), count($groupCards)),
        );
    }

    /**
     * The open polls the person may see, with their own responses (F-06, F-12, F-22).
     *
     * Follow-up polls pass through Poll::visibleTo(), so only accepted
     * participants and managers of the source event get them. The scope
     * also adds polls reached through a grant (F-26, F-27).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Poll>
     */
    private static function openPolls(User $user)
    {
        return Poll::query()
            ->whereNull('closed_at')
            ->visibleTo($user)
            ->with([
                'group',
                'event',
                'options.responses' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * The open polls the person has not answered for every option (F-06, F-12).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Poll>  $openPolls
     * @return \Illuminate\Database\Eloquent\Collection<int, Poll>
     */
    private static function pollsToAnswer($openPolls)
    {
        return $openPolls
            ->filter(fn (Poll $poll) => $poll->options->isNotEmpty() && $poll->options->contains(fn ($option) => $option->responses->isEmpty()))
            ->values();
    }

    /**
     * The upcoming events of the person's groups where they have the given status (F-10, F-11).
     *
     * Includes events reached through a grant (F-26, F-27). Loads the
     * accepted participations, which fill the avatar stack and the accepted count.
     *
     * @param  array<int, int>  $groupIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Event>
     */
    private static function eventsWith(User $user, array $groupIds, ParticipationStatus $status)
    {
        return Event::query()
            ->upcoming()
            ->where(fn (Builder $query) => $query
                ->whereIn('group_id', $groupIds)
                ->orWhere(fn (Builder $query) => $query->reachableThroughGrants($user)))
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
     * The active lists the person may open, in groups, events and standalone (F-13, F-22).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Roster>
     */
    private static function activeLists(User $user)
    {
        return DashboardListData::ordered(Roster::query()->visibleTo($user)->active()->with(DashboardListData::RELATIONS))->get();
    }
}
