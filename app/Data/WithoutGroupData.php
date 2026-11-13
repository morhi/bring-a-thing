<?php

namespace App\Data;

use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelData\Data;

class WithoutGroupData extends Data
{
    /**
     * @param  array<int, EventSummaryData>  $events
     * @param  array<int, DashboardPollData>  $polls
     * @param  array<int, DashboardListData>  $lists
     */
    public function __construct(
        public array $events,
        public array $polls,
        public array $lists,
    ) {}

    /**
     * Build the virtual group "Without groups" of a person (F-29).
     *
     * Past events, closed polls and past lists stay on the page, since it
     * is the only way back to them outside a group.
     */
    public static function forUser(User $user): self
    {
        $events = self::events($user)
            ->orderByDesc('date')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Event $event) => EventSummaryData::fromEvent($event))
            ->all();
        $polls = self::polls($user)
            ->with([
                'group',
                'options.responses' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->latest('id')
            ->get()
            ->map(fn (Poll $poll) => DashboardPollData::fromPoll($poll))
            ->all();
        $lists = DashboardListData::ordered(self::lists($user)->with(DashboardListData::RELATIONS))
            ->get()
            ->map(fn (Roster $roster) => DashboardListData::fromRoster($roster))
            ->all();

        return new self(events: $events, polls: $polls, lists: $lists);
    }

    /**
     * The counts for the dashboard card, or null when the virtual group would be empty (F-22, F-29).
     */
    public static function countsFor(User $user): ?DashboardWithoutGroupData
    {
        $counts = new DashboardWithoutGroupData(
            events_count: self::events($user)->count(),
            polls_count: self::polls($user)->count(),
            lists_count: self::lists($user)->count(),
        );

        if ($counts->events_count + $counts->polls_count + $counts->lists_count === 0) {
            return null;
        }

        return $counts;
    }

    /**
     * Events reached only through a grant, outside the person's own groups (F-26, F-27).
     *
     * @return Builder<Event>
     */
    private static function events(User $user): Builder
    {
        return Event::query()
            ->reachableThroughGrants($user)
            ->whereNotIn('group_id', $user->groups()->select('groups.id'));
    }

    /**
     * Polls reached only through a grant, outside the person's own groups (F-27).
     *
     * @return Builder<Poll>
     */
    private static function polls(User $user): Builder
    {
        return Poll::query()
            ->reachableThroughGrants($user)
            ->whereNotIn('group_id', $user->groups()->select('groups.id'));
    }

    /**
     * Lists without group and event the person may open: owned or claimed on (F-13, F-29).
     *
     * @return Builder<Roster>
     */
    private static function lists(User $user): Builder
    {
        return Roster::query()
            ->whereNull('group_id')
            ->whereNull('event_id')
            ->visibleTo($user);
    }
}
