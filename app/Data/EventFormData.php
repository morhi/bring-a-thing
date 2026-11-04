<?php

namespace App\Data;

use App\Models\Event;
use App\Models\Group;
use Spatie\LaravelData\Data;

class EventFormData extends Data
{
    public function __construct(
        public string $group_slug,
        public string $group_name,
        public int $member_count,
        public ?EventData $event,
        public ?int $parent_event_id = null,
        public ?string $parent_event_title = null,
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
}
