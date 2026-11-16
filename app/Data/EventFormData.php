<?php

namespace App\Data;

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
        public string $group_slug,
        public string $group_name,
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
     */
    public static function forGroup(Group $group, ?Event $event = null, ?Event $parent = null): self
    {
        return new self(
            group_slug: $group->slug,
            group_name: $group->name,
            member_count: $group->members()->count(),
            event: $event ? EventData::fromEvent($event) : null,
            parent_event_id: $parent?->id,
            parent_event_title: $parent?->title,
        );
    }

    /**
     * Build the "New event" page opened outside a group, offering every group of the user (F-10).
     *
     * Creating is allowed in every group the user is a member of, the same set as the group page's create buttons.
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
}
