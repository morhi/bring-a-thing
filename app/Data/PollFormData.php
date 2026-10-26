<?php

namespace App\Data;

use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use Spatie\LaravelData\Data;

class PollFormData extends Data
{
    public function __construct(
        public string $group_slug,
        public string $group_name,
        public int $member_count,
        public ?EventSummaryData $source_event,
        public ?PollData $poll,
    ) {}

    /**
     * Build the "New poll" page, or the same page in edit mode when a poll is given (F-06, F-08).
     *
     * In edit mode type and granularity are fixed (F-06), and each option
     * carries is_schedule_locked and can_remove for its row. member_count
     * feeds the audience note; for a follow-up poll it counts the poll's
     * audience and source_event names the event (F-12).
     */
    public static function forGroup(Group $group, ?Poll $poll = null): self
    {
        if ($poll?->event !== null) {
            return new self(
                group_slug: $group->slug,
                group_name: $group->name,
                member_count: $poll->audience()->count(),
                source_event: EventSummaryData::fromEvent($poll->event),
                poll: PollData::fromPoll($poll),
            );
        }

        return new self(
            group_slug: $group->slug,
            group_name: $group->name,
            member_count: $group->members()->count(),
            source_event: null,
            poll: $poll ? PollData::fromPoll($poll) : null,
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
            group_slug: $event->group->slug,
            group_name: $event->group->name,
            member_count: $event->followUpAudience()->count(),
            source_event: EventSummaryData::fromEvent($event),
            poll: null,
        );
    }
}
