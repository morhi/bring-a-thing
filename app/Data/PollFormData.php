<?php

namespace App\Data;

use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Spatie\LaravelData\Data;

class PollFormData extends Data
{
    /**
     * The time a new slot gets when no other slot exists yet (F-06).
     */
    public const DEFAULT_SLOT_START = '19:30';

    public const DEFAULT_SLOT_END = '22:00';

    /**
     * @param  array<int, RosterParentData>|null  $groups  Set on the page opened outside a group: the groups to pick from.
     */
    public function __construct(
        public string $group_slug,
        public string $group_name,
        public int $member_count,
        public ?EventSummaryData $source_event,
        public ?PollData $poll,
        public string $today,
        public string $default_slot_start,
        public string $default_slot_end,
        public ?array $groups = null,
    ) {}

    /**
     * Build the "New poll" page, or the same page in edit mode when a poll is given (F-06, F-08).
     *
     * In edit mode type and granularity are fixed (F-06), and each option
     * carries is_schedule_locked and can_remove for its row. member_count
     * feeds the audience note; for a follow-up poll it counts the poll's
     * audience and source_event names the event (F-12). today is the
     * server date, before which the option calendar takes no options.
     */
    public static function forGroup(Group $group, ?Poll $poll = null): self
    {
        if ($poll?->event !== null) {
            return new self(
                ...self::calendarDefaults(),
                group_slug: $group->slug,
                group_name: $group->name,
                member_count: $poll->audience()->count(),
                source_event: EventSummaryData::fromEvent($poll->event),
                poll: PollData::fromPoll($poll),
            );
        }

        return new self(
            ...self::calendarDefaults(),
            group_slug: $group->slug,
            group_name: $group->name,
            member_count: $group->members()->count(),
            source_event: null,
            poll: $poll ? PollData::fromPoll($poll) : null,
        );
    }

    /**
     * Build the "New poll" page opened outside a group, offering every group of the user (F-06).
     *
     * Creating is allowed in every group the user is a member of, the same set as the group page's create buttons.
     */
    public static function forCreate(User $user): self
    {
        return new self(
            ...self::calendarDefaults(),
            group_slug: '',
            group_name: '',
            member_count: 0,
            source_event: null,
            poll: null,
            groups: RosterParentData::forGroups($user),
        );
    }

    /**
     * Build the "New follow-up poll" page within the event (F-12).
     *
     * member_count counts the follow-up audience: the event's accepted
     * participants and managers. The form posts to events.polls.store.
     */
    public static function forEvent(Event $event): self
    {
        return new self(
            ...self::calendarDefaults(),
            group_slug: $event->group->slug,
            group_name: $event->group->name,
            member_count: $event->followUpAudience()->count(),
            source_event: EventSummaryData::fromEvent($event),
            poll: null,
        );
    }

    /**
     * The option calendar's server date and default slot time.
     *
     * @return array{today: string, default_slot_start: string, default_slot_end: string}
     */
    private static function calendarDefaults(): array
    {
        return [
            'today' => now()->toDateString(),
            'default_slot_start' => self::DEFAULT_SLOT_START,
            'default_slot_end' => self::DEFAULT_SLOT_END,
        ];
    }
}
