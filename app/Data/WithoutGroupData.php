<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelData\Data;

class WithoutGroupData extends Data
{
    /**
     * @param  array<int, DashboardListData>  $lists
     * @param  array<int, DashboardPollData>  $polls
     * @param  array<int, DashboardEventData>  $events
     */
    public function __construct(
        public array $lists,
        public array $polls,
        public array $events,
    ) {}

    /**
     * Build the virtual group "Without groups" of a person (F-29).
     *
     * Holds the person's own standalone lists, polls and events. Past and
     * closed items stay on the page, since it is the only way back to them
     * outside a group; open polls and upcoming events come first. Follow-up
     * polls and child events live on their event page. Items reached through
     * invite links are not the person's own, so they never show here (F-26,
     * F-27).
     */
    public static function forUser(User $user): self
    {
        $lists = DashboardListData::ordered(self::lists($user)->with(DashboardListData::RELATIONS))
            ->get()
            ->map(fn (Roster $roster) => DashboardListData::fromRoster($roster))
            ->all();

        $polls = self::polls($user)
            ->with(['group', 'options.responses' => fn ($query) => $query->where('user_id', $user->getKey())])
            ->orderByRaw('closed_at is not null')
            ->orderBy('id')
            ->get()
            ->map(fn (Poll $poll) => DashboardPollData::fromPoll($poll))
            ->all();

        $events = self::events($user)
            ->with([
                'group',
                'participations' => fn ($query) => $query->where('status', ParticipationStatus::Accepted)->with('user:id,name')->orderBy('id'),
            ])
            ->chronological('desc')
            ->get()
            ->sortBy(fn (Event $event) => $event->isPast() ? 1 : 0)
            ->values()
            ->map(fn (Event $event) => DashboardEventData::fromEvent($event))
            ->all();

        return new self(lists: $lists, polls: $polls, events: $events);
    }

    /**
     * The counts for the dashboard card, or null without an own standalone list, poll or event (F-22, F-29).
     */
    public static function countsFor(User $user): ?DashboardWithoutGroupData
    {
        $counts = new DashboardWithoutGroupData(
            lists_count: self::lists($user)->count(),
            polls_count: self::polls($user)->count(),
            events_count: self::events($user)->count(),
        );

        if ($counts->lists_count + $counts->polls_count + $counts->events_count === 0) {
            return null;
        }

        return $counts;
    }

    /**
     * The person's own lists without group and event (F-13, F-29).
     *
     * @return Builder<Roster>
     */
    private static function lists(User $user): Builder
    {
        return Roster::query()
            ->whereNull('group_id')
            ->whereNull('event_id')
            ->where('owner_id', $user->getKey());
    }

    /**
     * The person's own standalone polls, without follow-up polls (F-06, F-29).
     *
     * @return Builder<Poll>
     */
    private static function polls(User $user): Builder
    {
        return Poll::query()
            ->whereNull('group_id')
            ->whereNull('event_id')
            ->where('organizer_id', $user->getKey());
    }

    /**
     * The person's own standalone events, without child events (F-10, F-29).
     *
     * @return Builder<Event>
     */
    private static function events(User $user): Builder
    {
        return Event::query()
            ->whereNull('group_id')
            ->whereNull('parent_event_id')
            ->where('organizer_id', $user->getKey());
    }
}
