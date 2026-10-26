<?php

namespace App\Data;

use App\Models\Event;
use Spatie\LaravelData\Data;

class EventData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $location,
        public string $date,
        public ?string $starts_at,
        public ?string $end_date,
        public ?string $ends_at,
        public bool $is_past,
        public string $group_slug,
        public string $group_name,
        public int $organizer_id,
        public ?string $organizer_name,
        public ?string $origin_poll_slug,
        public ?EventSummaryData $parent_event,
    ) {}

    /**
     * Build the key details of an event (F-10).
     *
     * Dates are Y-m-d and times H:i; is_past is the event's only status.
     * id names the private broadcast channel event.{id} (F-21).
     */
    public static function fromEvent(Event $event): self
    {
        return new self(
            id: $event->id,
            slug: $event->slug,
            title: $event->title,
            description: $event->description,
            location: $event->location,
            date: $event->date->format('Y-m-d'),
            starts_at: EventSummaryData::time($event->starts_at),
            end_date: $event->end_date?->format('Y-m-d'),
            ends_at: EventSummaryData::time($event->ends_at),
            is_past: $event->isPast(),
            group_slug: $event->group->slug,
            group_name: $event->group->name,
            organizer_id: $event->organizer_id,
            organizer_name: $event->organizer?->name,
            origin_poll_slug: $event->originPollOption?->poll?->slug,
            parent_event: $event->parentEvent ? EventSummaryData::fromEvent($event->parentEvent) : null,
        );
    }
}
