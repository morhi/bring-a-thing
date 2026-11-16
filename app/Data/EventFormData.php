<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Spatie\LaravelData\Data;

class EventFormData extends Data
{
    /**
     * @param  array<int, RosterParentData>|null  $groups  Set on the page opened outside a group: the groups to pick from.
     */
    public function __construct(
        public ?string $group_slug,
        public ?string $group_name,
        public int $member_count,
        public ?EventData $event,
        public ?int $parent_event_id = null,
        public ?string $parent_event_title = null,
        public ?array $groups = null,
    ) {}

    /**
     * Build the "New event" page, or the same page in edit mode when an event is given (F-10).
     *
     * member_count feeds the audience note: every member is asked to accept or decline.
     * A standalone event has no group (F-29): group_slug and group_name are
     * null, and member_count counts its participants, or for a new
     * standalone sub-event the parent's participants with the organizer.
     */
    public static function forGroup(?Group $group, ?Event $event = null, ?Event $parent = null): self
    {
        return new self(
            group_slug: $group?->slug,
            group_name: $group?->name,
            member_count: $group?->members()->count() ?? self::standaloneCount($event, $parent),
            event: $event ? EventData::fromEvent($event) : null,
            parent_event_id: $parent?->id,
            parent_event_title: $parent?->title,
        );
    }

    /**
     * Build the "New event" page opened outside a group, offering every group of the user (F-10, F-29).
     *
     * Creating is allowed in every group the user is a member of, the same
     * set as the group page's create buttons, or standalone without a group;
     * the form posts to events.store-any with an optional group_id.
     */
    public static function forCreate(User $user): self
    {
        return new self(
            group_slug: '',
            group_name: '',
            member_count: 0,
            event: null,
            groups: RosterParentData::forGroups($user),
        );
    }

    /**
     * Count the people a standalone event asks: its participants, or for a new sub-event the parent's.
     */
    private static function standaloneCount(?Event $event, ?Event $parent): int
    {
        if ($event !== null) {
            return $event->participations()->count();
        }

        if ($parent === null) {
            return 1;
        }

        // The creating organizer manages the parent, so they already sit among its participants as a rule.
        return max(1, $parent->participations()
            ->whereIn('status', [ParticipationStatus::Accepted, ParticipationStatus::Pending])
            ->count());
    }
}
